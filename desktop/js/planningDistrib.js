/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

function printEqLogic(_eqLogic) {
  // Valeurs par défaut pour un nouvel équipement
  var conf = _eqLogic.configuration || {}
  if (!/^#[0-9a-fA-F]{6}$/.test(conf.color || '')) {
    $('.eqLogicAttr[data-l1key=configuration][data-l2key=color]').val('#EF9F27')
  }
}

$(document).off('click.planningDistrib')

$(document).on('click.planningDistrib', '.planningDistrib_selCmd', function () {
  var input = $(this).closest('.input-group').find('input')
  jeedom.cmd.getSelectModal({cmd: {type: $(this).data('type')}}, function (result) {
    input.val(result.human)
  })
})

/* ---------- Table des commandes ---------- */

function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = {configuration: {}}
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {}
  }
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">'
  tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="id"></span></td>'
  tr += '<td>'
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom de la commande}}">'
  tr += '</td>'
  tr += '<td>'
  tr += '<input class="cmdAttr" data-l1key="type" style="display:none">'
  tr += '<input class="cmdAttr" data-l1key="subType" style="display:none">'
  tr += '<span>' + init(_cmd.type) + ' / ' + init(_cmd.subType) + '</span>'
  tr += '</td>'
  tr += '<td>'
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked>{{Afficher}}</label> '
  if (init(_cmd.type) == 'info' && init(_cmd.subType) != 'string') {
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized">{{Historiser}}</label>'
  }
  tr += '</td>'
  tr += '<td><span class="cmdAttr" data-l1key="htmlstate"></span></td>'
  tr += '<td>'
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> '
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>'
  }
  tr += '</td>'
  tr += '</tr>'
  $('#table_cmd tbody').append(tr)
  var $tr = $('#table_cmd tbody tr').last()
  $tr.setValues(_cmd, '.cmdAttr')
}
