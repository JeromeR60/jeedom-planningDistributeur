<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../../../../core/php/core.inc.php';

class planningDistrib extends eqLogic {

  const SLOTS = 24;      // créneaux d'une heure par jour
  const LEN = 168;       // 7 jours x 24 créneaux
  const TOLERANCE = 10;  // minutes après l'heure pendant lesquelles une distribution manquée est rattrapée
  const MAX_DOSE = 50;   // borne haute autorisée pour la dose max

  public static $_widgetPossibility = array('custom' => true);

  /* ------------------------------------------------------------------ */
  /*  Cron : contrôle chaque minute                                      */
  /* ------------------------------------------------------------------ */

  public static function cron() {
    foreach (eqLogic::byType(__CLASS__, true) as $eqLogic) {
      try {
        $eqLogic->checkSchedule();
      } catch (Exception $e) {
        log::add(__CLASS__, 'error', $eqLogic->getHumanName() . ' : ' . $e->getMessage());
      }
    }
  }

  /* ------------------------------------------------------------------ */
  /*  Doses                                                              */
  /* ------------------------------------------------------------------ */

  public function getDoseMin() {
    $min = (int)$this->getConfiguration('doseMin', 1);
    return max(1, min($min, self::MAX_DOSE));
  }

  public function getDoseMax() {
    $max = (int)$this->getConfiguration('doseMax', 12);
    return max($this->getDoseMin(), min($max, self::MAX_DOSE));
  }

  /** Ramène une dose dans les bornes : 0 = rien, sinon entre min et max */
  public function clampDose($_dose) {
    $dose = (int)$_dose;
    if ($dose <= 0) return 0;
    return max($this->getDoseMin(), min($dose, $this->getDoseMax()));
  }

  /* ------------------------------------------------------------------ */
  /*  Planning                                                           */
  /* ------------------------------------------------------------------ */

  /** Planning : tableau de 168 doses (0 = rien), jour 0 = lundi */
  public function getPlan() {
    $raw = (string)$this->getConfiguration('planning', '');
    $values = $raw === '' ? array() : explode(',', $raw);
    if (count($values) != self::LEN) {
      return array_fill(0, self::LEN, 0);
    }
    $plan = array();
    foreach ($values as $value) {
      $plan[] = $this->clampDose($value);
    }
    return $plan;
  }

  /** Format partagé avec le widget : 168 doses séparées par des virgules */
  public function serializePlanning() {
    return implode(',', $this->getPlan());
  }

  public static function slotIndex($_ts = null) {
    if ($_ts === null) $_ts = time();
    return ((int)date('N', $_ts) - 1) * self::SLOTS + (int)date('G', $_ts);
  }

  /** Prochaine distribution après l'heure en cours : array(ts, dose) ou null */
  public function nextDistribution() {
    $plan = $this->getPlan();
    $idx = self::slotIndex();
    $hourStart = mktime((int)date('G'), 0, 0);
    for ($n = 1; $n <= self::LEN; $n++) {
      $dose = $plan[($idx + $n) % self::LEN];
      if ($dose > 0) {
        return array('ts' => $hourStart + $n * 3600, 'dose' => $dose);
      }
    }
    return null;
  }

  public static function formatTime($_ts) {
    if ($_ts <= 0) return '';
    $days = array('lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.');
    $txt = date('H:i', $_ts);
    if (date('Y-m-d', $_ts) != date('Y-m-d')) {
      $txt = $days[(int)date('N', $_ts) - 1] . ' ' . $txt;
    }
    return $txt;
  }

  /* ------------------------------------------------------------------ */
  /*  Infos                                                              */
  /* ------------------------------------------------------------------ */

  /** Total distribué aujourd'hui (remis à zéro au changement de jour) */
  public function getTodayDone() {
    if ($this->getConfiguration('doneDate', '') != date('Y-m-d')) {
      return 0;
    }
    return (int)$this->getConfiguration('doneTotal', 0);
  }

  public function getTodayPlanned() {
    $plan = $this->getPlan();
    $day = (int)date('N') - 1;
    return array_sum(array_slice($plan, $day * self::SLOTS, self::SLOTS));
  }

  public function refreshInfos() {
    $this->checkAndUpdateCmd('planning', $this->serializePlanning());
    $this->checkAndUpdateCmd('todayPlanned', $this->getTodayPlanned());
    $this->checkAndUpdateCmd('todayDone', $this->getTodayDone());
    $next = $this->nextDistribution();
    $this->checkAndUpdateCmd('next', $next !== null ? self::formatTime($next['ts']) . ' - ' . $next['dose'] : '');
    $lastTs = (int)$this->getConfiguration('lastTs', 0);
    $this->checkAndUpdateCmd('lastDose', (int)$this->getConfiguration('lastDose', 0));
    $this->checkAndUpdateCmd('lastTime', self::formatTime($lastTs));
  }

  /* ------------------------------------------------------------------ */
  /*  Distribution                                                       */
  /* ------------------------------------------------------------------ */

  /**
   * Distribue la dose de l'heure en cours, une seule fois par heure.
   * Une distribution manquée (Jeedom arrêté à l'heure pile) est rattrapée pendant TOLERANCE minutes.
   */
  public function checkSchedule() {
    if ($this->getIsEnable() != 1) return;
    $this->refreshInfos();

    if ((int)date('i') >= self::TOLERANCE) return;
    $key = date('Y-m-d H');
    if ($this->getConfiguration('lastKey', '') === $key) return;

    $plan = $this->getPlan();
    $dose = $plan[self::slotIndex()];
    if ($dose <= 0) return;

    // On marque l'heure comme traitée avant d'envoyer l'ordre : jamais de double distribution
    $this->setConfiguration('lastKey', $key);
    $this->save(true);
    $this->distribute($dose, 'planning');
  }

  /** Envoie la dose à la commande de distribution et met à jour les infos */
  public function distribute($_dose, $_source = 'manuel') {
    $dose = $this->clampDose($_dose);
    if ($dose <= 0) {
      throw new Exception(__('Dose invalide', __FILE__) . ' : ' . $_dose);
    }
    $cmd = self::cmdFromConfig($this->getConfiguration('distribCmd', ''));
    if (!is_object($cmd) || $cmd->getType() != 'action') {
      throw new Exception(__('Commande de distribution invalide ou absente', __FILE__));
    }
    switch ($cmd->getSubType()) {
      case 'slider':
        $cmd->execCmd(array('slider' => $dose));
        break;
      case 'message':
        $cmd->execCmd(array('title' => '', 'message' => (string)$dose));
        break;
      case 'select':
        $cmd->execCmd(array('select' => (string)$dose));
        break;
      default:
        throw new Exception(__('La commande de distribution doit être de type curseur, message ou liste', __FILE__));
    }

    $today = date('Y-m-d');
    $done = $this->getConfiguration('doneDate', '') == $today ? (int)$this->getConfiguration('doneTotal', 0) : 0;
    $this->setConfiguration('doneDate', $today);
    $this->setConfiguration('doneTotal', $done + $dose);
    $this->setConfiguration('lastDose', $dose);
    $this->setConfiguration('lastTs', time());
    $this->save(true);
    $this->refreshInfos();
    log::add(__CLASS__, 'info', $this->getHumanName() . ' Dose ' . $dose . ' distribuée (' . $_source . ')');
  }

  public static function cmdFromConfig($_value) {
    $id = trim(str_replace('#', '', (string)$_value));
    if ($id === '' || !is_numeric($id)) return null;
    $cmd = cmd::byId($id);
    return is_object($cmd) ? $cmd : null;
  }

  /* ------------------------------------------------------------------ */
  /*  Actions                                                            */
  /* ------------------------------------------------------------------ */

  public function setPlanningString($_str) {
    $values = explode(',', trim((string)$_str));
    if (count($values) != self::LEN) {
      throw new Exception(__('Planning invalide : 168 valeurs attendues', __FILE__) . ' (' . count($values) . ')');
    }
    $min = $this->getDoseMin();
    $max = $this->getDoseMax();
    $plan = array();
    foreach ($values as $value) {
      $value = trim($value);
      if (!ctype_digit($value)) {
        throw new Exception(__('Planning invalide : valeur non numérique', __FILE__) . ' ' . $value);
      }
      $dose = (int)$value;
      if ($dose != 0 && ($dose < $min || $dose > $max)) {
        throw new Exception(__('Planning invalide : dose hors limites', __FILE__) . ' ' . $dose . ' (' . $min . '-' . $max . ')');
      }
      $plan[] = $dose;
    }
    $this->setConfiguration('planning', implode(',', $plan));
    $this->save(true);
    $this->refreshInfos();
  }

  /* ------------------------------------------------------------------ */
  /*  Cycle de vie                                                       */
  /* ------------------------------------------------------------------ */

  public function preSave() {
    $min = (int)$this->getConfiguration('doseMin', 1);
    $max = (int)$this->getConfiguration('doseMax', 12);
    if ($min < 1) $min = 1;
    if ($max > self::MAX_DOSE) $max = self::MAX_DOSE;
    if ($max < $min) {
      throw new Exception(__('La dose max doit être supérieure ou égale à la dose min', __FILE__));
    }
    $this->setConfiguration('doseMin', $min);
    $this->setConfiguration('doseMax', $max);
  }

  public function postSave() {
    $order = 0;
    $planning = $this->createCmd('planning', __('Planning', __FILE__), 'info', 'string', $order++, true);
    $setPlanning = $this->createCmd('setPlanning', __('Set planning', __FILE__), 'action', 'message', $order++, false);
    $planned = $this->createCmd('todayPlanned', __('Prévu aujourd\'hui', __FILE__), 'info', 'numeric', $order++, false);
    $done = $this->createCmd('todayDone', __('Distribué aujourd\'hui', __FILE__), 'info', 'numeric', $order++, false);
    $this->createCmd('next', __('Prochaine distribution', __FILE__), 'info', 'string', $order++, false);
    $lastDose = $this->createCmd('lastDose', __('Dernière dose', __FILE__), 'info', 'numeric', $order++, false);
    $lastTime = $this->createCmd('lastTime', __('Dernière distribution', __FILE__), 'info', 'string', $order++, false);

    // Le planning stocké est ramené dans les nouvelles bornes
    $current = (string)$this->getConfiguration('planning', '');
    $clamped = implode(',', $this->getPlan());
    if ($current !== $clamped) {
      $this->setConfiguration('planning', $clamped);
      $this->save(true);
    }

    // Paramètres du widget, recalculés à chaque sauvegarde
    $params = array(
      'set_id' => $setPlanning->getId(),
      'done_id' => $done->getId(),
      'last_dose_id' => $lastDose->getId(),
      'last_time_id' => $lastTime->getId(),
      'min' => $this->getDoseMin(),
      'max' => $this->getDoseMax(),
      'color' => ltrim(preg_match('/^#[0-9a-fA-F]{6}$/', $this->getConfiguration('color', '')) ? $this->getConfiguration('color') : '#EF9F27', '#'),
      'width' => (int)$this->getConfiguration('width', 360) ?: 360,
    );
    // Nom de widget propre au plugin : Jeedom met les widgets en cache par nom de fichier,
    // un nom partagé avec un autre plugin (ex. planningThermo) afficherait le mauvais widget.
    $changed = false;
    foreach (array('dashboard', 'mobile') as $version) {
      if ($planning->getTemplate($version) == 'planningDistrib::planning') {
        $planning->setTemplate($version, 'planningDistrib::planningDistrib');
        $changed = true;
      }
    }
    if ($planning->getDisplay('parameters') != $params) {
      $planning->setDisplay('parameters', $params);
      $changed = true;
    }
    if ($changed) {
      $planning->save();
    }

    if ($this->getIsEnable() == 1) {
      $this->refreshInfos();
    }
  }

  private function createCmd($_logicalId, $_name, $_type, $_subType, $_order, $_visible) {
    $cmd = $this->getCmd(null, $_logicalId);
    if (!is_object($cmd)) {
      $cmd = new planningDistribCmd();
      $cmd->setLogicalId($_logicalId);
      $cmd->setEqLogic_id($this->getId());
      $cmd->setName($_name);
      $cmd->setType($_type);
      $cmd->setSubType($_subType);
      $cmd->setOrder($_order);
      $cmd->setIsVisible($_visible ? 1 : 0);
      if ($_type == 'info') {
        $cmd->setIsHistorized(0);
      }
      if ($_logicalId == 'planning') {
        $cmd->setTemplate('dashboard', 'planningDistrib::planningDistrib');
        $cmd->setTemplate('mobile', 'planningDistrib::planningDistrib');
        $cmd->setDisplay('showNameOndashboard', 0);
        $cmd->setDisplay('showNameOnmobile', 0);
      }
      if ($_logicalId == 'setPlanning') {
        $cmd->setDisplay('title_disable', 1);
      }
      $cmd->save();
    }
    return $cmd;
  }
}

class planningDistribCmd extends cmd {

  public static $_widgetPossibility = array('custom' => true);

  public function dontRemoveCmd() {
    return true;
  }

  public function execute($_options = array()) {
    $eqLogic = $this->getEqLogic();
    $message = isset($_options['message']) ? trim($_options['message']) : '';
    switch ($this->getLogicalId()) {
      case 'setPlanning':
        $eqLogic->setPlanningString($message);
        break;
    }
  }
}
