"use strict";

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

function _toConsumableArray(arr) { return _arrayWithoutHoles(arr) || _iterableToArray(arr) || _unsupportedIterableToArray(arr) || _nonIterableSpread(); }

function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }

function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }

function _iterableToArray(iter) { if (typeof Symbol !== "undefined" && iter[Symbol.iterator] != null || iter["@@iterator"] != null) return Array.from(iter); }

function _arrayWithoutHoles(arr) { if (Array.isArray(arr)) return _arrayLikeToArray(arr); }

function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) { arr2[i] = arr[i]; } return arr2; }

/*
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 11 2018
 *
 */
var idUrl = 'module-extended-c-d-rs';
var idForm = 'module-extended-cdr-form';
var className = 'ModuleExtendedCDRs';
var inputClassName = 'mikopbx-module-input';
var listenedIDs = [];
/* global globalRootUrl, globalTranslate, Form, Config, $ */

var ModuleExtendedCDRs = {
  $formObj: $('#' + idForm),
  $checkBoxes: $('#' + idForm + ' .ui.checkbox'),
  $dropDowns: $('#' + idForm + ' .ui.dropdown'),
  saveTableAJAXUrl: globalRootUrl + idUrl + "/saveTableData",
  deleteRecordAJAXUrl: globalRootUrl + idUrl + "/delete",
  $disabilityFields: $('#' + idForm + '  .disability'),
  $statusToggle: $('#module-status-toggle'),
  $moduleStatus: $('#status'),

  /**
   * The call detail records table element.
   * @type {jQuery}
   */
  $cdrTable: $('#cdr-table'),
  $outgoingEmployeeCalls: $('#OutgoingEmployeeCalls-table'),

  /**
   * The global search input element.
   * @type {jQuery}
   */
  $globalSearch: $('#globalsearch'),

  /**
   * The date range selector element.
   * @type {jQuery}
   */
  $dateRangeSelector: $('#date-range-selector'),

  /**
   * The data table object.
   * @type {Object}
   */
  dataTable: {},

  /**
   * An array of players.
   * @type {Array}
   */
  players: [],

  /**
   * Field validation rules
   * https://semantic-ui.com/behaviors/form.html
   */
  validateRules: {},

  /**
   * On page load we init some Semantic UI library
   */
  initialize: function initialize() {
    //////
    // Удаляем отступы контейнера.
    $('#main-content-container').removeClass('container');
    $('#module-status-toggle-segment').hide();
    $('.ui.clearing.hidden.divider').remove();
    $("h1.header i.puzzle.icon").removeClass('puzzle').addClass('th').css('cursor', 'pointer').popup({
      inline: true,
      popup: $('#menu-reports'),
      target: "h1.ui.header",
      position: 'bottom left',
      hoverable: true,
      delay: {
        show: 300,
        hide: 800
      }
    });
    $('#content-frame').css('display', 'none'); // Окончание форматирования базовой страницы
    //////

    ModuleExtendedCDRs.changeReportVariant($('#currentReportNameID').val());
    ModuleExtendedCDRs.initializeDateRangeSelector(); // инициализируем чекбоксы и выподающие менюшки

    window[className].$checkBoxes.checkbox();
    window[className].$dropDowns.dropdown({
      onChange: ModuleExtendedCDRs.applyFilter
    });
    window.addEventListener('ModuleStatusChanged', window[className].checkStatusToggle);
    window[className].initializeForm();
    $('.menu .item').tab();
    $('#typeCall.menu a.item').on('click', function (e) {
      ModuleExtendedCDRs.applyFilter();
    });
    $('#createExcelButton').on('click', function (e) {
      ModuleExtendedCDRs.startCreateExcelPDF('xlsx');
    });
    $('#createPdfButton').on('click', function (e) {
      ModuleExtendedCDRs.startCreateExcelPDF('pdf');
    });
    $('#downloadRecords').on('click', function (e) {
      var encodedSearch = encodeURIComponent(ModuleExtendedCDRs.getSearchText());
      var url = "".concat(window.location.origin, "/pbxcore/api/modules/").concat(className, "/downloads?search=").concat(encodedSearch);
      window.open(url, '_blank');
    });
    $('#saveSearchSettings').on('click', function (e) {
      ModuleExtendedCDRs.saveSearchSettings();
    });
    $("div.column h4").on('click', function (e) {
      var reportId = $(this).attr('id');
      $("h1.header i.th.icon").popup('hide');
      ModuleExtendedCDRs.changeReportVariant(reportId);
    });
    ModuleExtendedCDRs.$globalSearch.on('keyup', function (e) {
      if (e.keyCode === 13 || e.keyCode === 8 || ModuleExtendedCDRs.$globalSearch.val().length === 0) {
        ModuleExtendedCDRs.applyFilter();
      }
    });
    ModuleExtendedCDRs.$formObj.keydown(function (event) {
      if (event.keyCode === 13) {
        event.preventDefault();
        return false;
      }
    });
    ModuleExtendedCDRs.$outgoingEmployeeCalls.dataTable({
      search: {
        search: ModuleExtendedCDRs.getSearchText()
      },
      serverSide: true,
      processing: true,
      info: false,
      columnDefs: [{
        defaultContent: "",
        targets: "_all"
      }],
      ajax: {
        url: "".concat(globalRootUrl).concat(idUrl, "/getOutgoingEmployeeCalls"),
        type: 'POST'
      },
      paging: true,
      sDom: 'rtip',
      deferRender: true,
      pageLength: ModuleExtendedCDRs.calculatePageLength(),

      /**
       * Constructs the CDR row.
       * @param {HTMLElement} row - The row element.
       * @param {Array} data - The row data.
       */
      createdRow: function createdRow(row, data) {
        $('td', row).eq(0).html(data.callerId);
        $('td', row).eq(1).html(data.number).addClass('active');
        $('td', row).eq(2).html(data.billHourCalls);
        $('td', row).eq(3).html(data.billMinCalls);
        $('td', row).eq(4).html(data.billSecCalls);
        $('td', row).eq(5).html(data.countCalls);
      },
      drawCallback: function drawCallback(settings) {
        var pagination = $(this).closest('.dataTables_wrapper').find('.dataTables_paginate');

        if (settings._iDisplayLength >= settings.fnRecordsDisplay()) {
          pagination.hide();
        } else {
          pagination.show();
        }
      },
      language: SemanticLocalization.dataTableLocalisation,
      ordering: false
    });
    ModuleExtendedCDRs.$cdrTable.dataTable({
      search: {
        search: ModuleExtendedCDRs.getSearchText()
      },
      serverSide: true,
      processing: true,
      info: false,
      columnDefs: [{
        defaultContent: "-",
        targets: "_all"
      }],
      ajax: {
        url: "".concat(globalRootUrl).concat(idUrl, "/getHistory"),
        type: 'POST',
        dataSrc: function dataSrc(json) {
          $('a.item[data-tab="all-calls"] b').html(': ' + json.recordsFiltered);
          $('a.item[data-tab="incoming-calls"] b').html(': ' + json.recordsIncoming);
          $('a.item[data-tab="missed-calls"] b').html(': ' + json.recordsMissed);
          $('a.item[data-tab="outgoing-calls"] b').html(': ' + json.recordsOutgoing);
          var typeCall = $('#typeCall a.item.active').attr('data-tab');

          if (typeCall === 'incoming-calls') {
            json.recordsFiltered = json.recordsIncoming;
          } else if (typeCall === 'missed-calls') {
            json.recordsFiltered = json.recordsMissed;
          } else if (typeCall === 'outgoing-calls') {
            json.recordsFiltered = json.recordsOutgoing;
          }

          return json.data;
        }
      },
      paging: true,
      sDom: 'rtip',
      deferRender: true,
      stripeClasses: ['striped'],
      pageLength: ModuleExtendedCDRs.calculatePageLength(),

      /**
       * Constructs the CDR row.
       * @param {HTMLElement} row - The row element.
       * @param {Array} data - The row data.
       */
      createdRow: function createdRow(row, data) {
        var detailedIcon = '';

        if (data.DT_RowClass.indexOf("detailed") >= 0) {
          detailedIcon = '<i class="icon caret down"></i>';
        }

        data.typeCall = "".concat(data.typeCall);

        if (data.typeCall === '1') {
          $('td', row).eq(0).html('<i class="custom-outgoing-icon-15x15"></i>' + detailedIcon);
        } else if (data.typeCall === '2') {
          $('td', row).eq(0).html('<i class="custom-incoming-icon-15x15"></i>' + detailedIcon);
        } else if (data.typeCall === '3') {
          $('td', row).eq(0).html('<i class="custom-missed-icon-15x15"></i>' + detailedIcon);
        } else {
          $('td', row).eq(0).html('' + detailedIcon);
        }

        $('td', row).eq(1).html(data[0]).addClass('right aligned');
        $('td', row).eq(2).html(data[1]).attr('data-phone', data[1]).addClass('need-update').addClass('right aligned');
        $('td', row).eq(3).html(data[2]).attr('data-phone', data[2]).addClass('need-update');
        var duration = data[3];

        if (data.ids !== '') {
          duration += '<i data-ids="' + data.ids + '" class="file alternate outline icon">';
        }

        var lineText = data.line;

        if (data.did !== "") {
          lineText = "".concat(data.line, " <a class=\"ui mini basic label\">").concat(data.did, "</a>");
        }

        $('td', row).eq(4).html(lineText).addClass('right aligned');
        $('td', row).eq(5).html(data.waitTime).addClass('right aligned');
        $('td', row).eq(6).html(duration).addClass('right aligned');
        $('td', row).eq(7).html(data.stateCall).addClass('right aligned');
      },

      /**
       * Draw event - fired once the table has completed a draw.
       */
      drawCallback: function drawCallback(settings) {
        Extensions.updatePhonesRepresent('need-update');
        listenedIDs.forEach(function (id) {
          var element = $("[id=\"".concat(id, "\"]"));

          if (element.length) {
            element.removeClass('warning').addClass('positive');
          }
        });
        var pagination = $(this).closest('.dataTables_wrapper').find('.dataTables_paginate');

        if (settings._iDisplayLength >= settings.fnRecordsDisplay()) {
          pagination.hide();
        } else {
          pagination.show();
        }
      },
      language: SemanticLocalization.dataTableLocalisation,
      ordering: false
    });
    ModuleExtendedCDRs.dataTable = ModuleExtendedCDRs.$cdrTable.DataTable();
    ModuleExtendedCDRs.dataTable.on('draw', function () {
      ModuleExtendedCDRs.$globalSearch.closest('div').removeClass('loading');
    });
    ModuleExtendedCDRs.$cdrTable.on('click', 'tr.negative', function (e) {
      // let filter = $(e.target).attr('data-phone');
      // if (filter !== undefined && filter !== '') {
      // 	ModuleExtendedCDRs.$globalSearch.val(filter)
      // 	ModuleExtendedCDRs.applyFilter();
      // 	return;
      // }
      var ids = $(e.target).attr('data-ids');

      if (ids !== undefined && ids !== '') {
        window.location = "".concat(globalRootUrl, "system-diagnostic/index/?filename=asterisk/verbose&filter=").concat(ids);
      }
    }); // Add event listener for opening and closing details

    ModuleExtendedCDRs.$cdrTable.on('click', 'tr.detailed', function (e) {
      var ids = $(e.target).attr('data-ids');

      if (ids !== undefined && ids !== '') {
        window.location = "".concat(globalRootUrl, "system-diagnostic/index/?filename=asterisk/verbose&filter=").concat(ids);
        return;
      } // let filter = $(e.target).attr('data-phone');
      // if (filter !== undefined && filter !== '') {
      // 	ModuleExtendedCDRs.$globalSearch.val(filter)
      // 	ModuleExtendedCDRs.applyFilter();
      // 	return;
      // }


      var tr = $(e.target).closest('tr');
      var row = ModuleExtendedCDRs.dataTable.row(tr);

      if (row.length === 0) {
        return;
      }

      if (tr.hasClass('shown')) {
        // This row is already open - close it
        $('tr[data-row-id="' + tr.attr('id') + '-detailed"').remove();
        tr.removeClass('shown');
      } else {
        // Open this row
        tr.after(ModuleExtendedCDRs.showRecords(row.data(), tr.attr('id')));
        tr.addClass('shown');
        $('tr[data-row-id="' + tr.attr('id') + '-detailed"').each(function (index, playerRow) {
          var id = $(playerRow).attr('id');
          return new CDRPlayer(id);
        });
        Extensions.updatePhonesRepresent('need-update');
        listenedIDs.forEach(function (id) {
          var element = $("[id=\"".concat(id, "\"]"));

          if (element.length) {
            element.removeClass('warning').addClass('positive');
          }

          element = $("[data-row-id=\"".concat(id, "\"]"));

          if (element.length) {
            element.removeClass('warning').addClass('positive');
          }
        });
      }
    });
    ModuleExtendedCDRs.updateSettings();
    ModuleExtendedCDRs.applyFilter();
    window[className].updateSyncState();
    setInterval(window[className].updateSyncState, 5000);
  },
  changeReportVariant: function changeReportVariant(reportNameID) {
    var currentVariantId = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : '';
    $("table[data-report-name!=\"\"]").hide();
    $("table[data-report-name=\"".concat(reportNameID, "\"]")).css('width', '').show();
    $('#currentReportNameID').val(reportNameID);
    $('#currentVariantId').val(currentVariantId);
    $("h1.header div.content").contents().filter(function () {
      return this.nodeType === 3 && this.nodeValue.trim() !== '';
    }).each(function () {
      this.nodeValue = $("#".concat($('#currentReportNameID').val())).text();
    });
    ModuleExtendedCDRs.updateSettings();
  },
  updateSettings: function updateSettings() {
    var currentVariantId = $('#currentVariantId').val();
    var settings = {};

    if (currentVariantId === '') {
      settings = JSON.parse(decodeURIComponent($("#".concat($('#currentReportNameID').val())).attr('data-search-text')));
    } else {// settings = JSON.parse(decodeURIComponent($(`#${$('#currentReportNameID').val()}`).attr('data-search-text')));
      // TODO
    }

    if (settings.dateRangeSelector !== undefined && settings.dateRangeSelector !== '') {
      var periods = ModuleExtendedCDRs.getStandardPeriods();
      var defPeriod = [moment(), moment()];

      if (periods[settings.dateRangeSelector] !== undefined) {
        defPeriod = periods[settings.dateRangeSelector];
      }

      ModuleExtendedCDRs.$dateRangeSelector.attr('data-start', defPeriod[0].format('YYYY/MM/DD'));
      ModuleExtendedCDRs.$dateRangeSelector.attr('data-end', moment(defPeriod[1].format('YYYYMMDD')).endOf('day').format('YYYY/MM/DD'));
      ModuleExtendedCDRs.$dateRangeSelector.val("".concat(defPeriod[0].format('DD/MM/YYYY'), " - ").concat(defPeriod[1].format('DD/MM/YYYY')));
    }

    if (settings.globalSearch !== undefined) {
      ModuleExtendedCDRs.$globalSearch.val(settings.globalSearch);
    }

    $('#additionalFilter').dropdown('clear');

    if (settings.additionalFilter !== undefined) {
      $('#additionalFilter').dropdown('set selected', settings.additionalFilter.split(' '));
    }

    if (settings.typeCall !== undefined) {
      $('#typeCall.menu a.item').tab('change tab', settings.typeCall);
    } else {
      $('#typeCall.menu a.item').tab('change tab', 'all-calls');
    }
  },

  /**
   *
   */
  updateSyncState: function updateSyncState() {
    // Выполняем GET-запрос
    var divProgress = $("#sync-progress");
    $.ajax({
      url: "".concat(globalRootUrl).concat(idUrl, "/getState"),
      method: 'GET',
      success: function success(response) {
        if (response.stateData.lastId - response.stateData.nowId > 0) {
          divProgress.show();
        } else {
          divProgress.hide();
        }

        divProgress.progress({
          total: response.stateData.lastId,
          value: response.stateData.nowId,
          text: {
            active: globalTranslate.repModuleExtendedCDRs_syncState
          },
          error: function error(jqXHR, textStatus, errorThrown) {
            // Обработка ошибки
            console.error('Ошибка запроса:', textStatus, errorThrown);
          }
        });
      }
    });
  },

  /**
   * Shows a set of call records when a row is clicked.
   * @param {Array} data - The row data.
   * @returns {string} The HTML representation of the call records.
   */
  showRecords: function showRecords(data, id) {
    var htmlPlayer = '';
    data[4].forEach(function (record, i) {
      var srcAudio = '';
      var srcDownloadAudio = '';

      if (!(record.recordingfile === undefined || record.recordingfile === null || record.recordingfile.length === 0)) {
        var recordFileName = encodeURIComponent(record.prettyFilename);
        var recordFileUri = encodeURIComponent(record.recordingfile);
        srcAudio = "/pbxcore/api/cdr/v2/playback?view=".concat(recordFileUri);
        srcDownloadAudio = "/pbxcore/api/cdr/v2/playback?view=".concat(recordFileUri, "&download=1&filename=").concat(recordFileName, ".mp3");
      }

      htmlPlayer += "\n\t\t\t<tr id=\"".concat(record.id, "\" data-row-id=\"").concat(id, "-detailed\" class=\"warning detailed odd shown\" role=\"row\">\n\t\t\t\t<td></td>\n\t\t\t\t<td class=\"right aligned\">").concat(record.start, "</td>\n\t\t\t\t<td data-phone=\"").concat(record.src_num, "\" class=\"right aligned need-update\">").concat(record.src_num, "</td>\n\t\t\t   \t<td data-phone=\"").concat(record.dst_num, "\" class=\"left aligned need-update\">").concat(record.dst_num, "</td>\n\t\t\t\t<td class=\"right aligned\">\t\t\t\n\t\t\t\t</td>\n\t\t\t\t<td class=\"right aligned\">").concat(record.waitTime, "</td>\n\t\t\t\t<td class=\"right aligned\">\n\t\t\t\t\t<i class=\"ui icon play\"></i>\n\t\t\t\t\t<audio preload=\"metadata\" id=\"audio-player-").concat(record.id, "\" src=\"").concat(srcAudio, "\" onplay=\"ModuleExtendedCDRs.audioPlayHandler(event)\"></audio>\n\t\t\t\t\t").concat(record.billsec, "\n\t\t\t\t\t<i class=\"ui icon download\" data-value=\"").concat(srcDownloadAudio, "\" onclick=\"ModuleExtendedCDRs.audioPlayHandler(event)\"></i>\n\t\t\t\t</td>\n\t\t\t\t<td class=\"right aligned\" data-state-index=\"").concat(record.stateCallIndex, "\">").concat(record.stateCall, "</td>\n\t\t\t</tr>");
    });
    return htmlPlayer;
  },
  audioPlayHandler: function audioPlayHandler(event) {
    var detailRow = $(event.target).closest('tr');
    detailRow.removeClass('warning');
    detailRow.addClass('positive');
    var callIdDetail = detailRow.attr('data-row-id');
    listenedIDs.push(callIdDetail);
    var callId = callIdDetail.replace('-detailed', '');
    var allPositive = true;
    $('[data-row-id="' + callIdDetail + '"]').each(function () {
      if (!$(this).hasClass('positive')) {
        allPositive = false;
        return false;
      }
    });

    if (allPositive) {
      $("[id=\"".concat(callId, "\"]")).addClass('positive');
      listenedIDs.push(callId);
    }

    listenedIDs = _toConsumableArray(new Set(listenedIDs));
  },
  calculatePageLength: function calculatePageLength() {
    // Calculate row height
    var rowHeight = ModuleExtendedCDRs.$cdrTable.find('tbody > tr').first().outerHeight(); // Calculate window height and available space for table

    var windowHeight = window.innerHeight;
    var headerFooterHeight = 400; // Estimate height for header, footer, and other elements
    // Calculate new page length

    return Math.max(Math.floor((windowHeight - headerFooterHeight) / rowHeight), 5);
  },
  additionalExportFunctions: function additionalExportFunctions() {
    var body = $('body');
    body.on('click', '#add-new-button', function (e) {
      var id = 'none-' + Date.now();
      var newRow = $('<tr>').attr('id', id);
      var nameCell = $('<td>').attr('data-label', 'name').addClass('right aligned').html('<div class="ui mini icon input"><input type="text" placeholder="" value=""></div>');
      var usersCell = $('<td>').attr('data-label', 'users').html('<div class="ui multiple dropdown">' + $('#users-selector').html() + '<div>');
      var buttonsCell = $('<td>').attr('data-label', 'buttons').html('<div class="ui buttons">\n' + '                  <button class="compact ui icon basic button" data-action="settings" onclick="ModuleExtendedCDRs.showRuleOptions(\'' + id + '\')">\n' + '                    <i class="cog icon"></i>\n' + '                  </button>\n' + '                  <button class="compact ui icon basic button" data-action="remove" onclick="ModuleExtendedCDRs.removeRule(\'' + id + '\')">\n' + '                    <i class="icon trash red"></i>\n' + '                  </button>\n' + '                </div>');
      var headersCell = $('<td>').attr('data-label', 'headers').addClass('right aligned').attr('style', 'display: none').html('<div class="ui modal segment" data-id="' + id + '">\n' + '                  <i class="close icon"></i>\n' + '                    <div class="ui form">\n' + '                      <div class="field">\n' + '                        <label>HTTP Headers</label>\n' + '                        <textarea></textarea>\n' + '                      </div>\n' + '                      <div class="field">\n' + '                        <label>URL</label>\n' + '                        <div class="ui icon input"><input data-label="dstUrl" type="text" placeholder="" value=""></div>\n' + '                      </div>\n' + '                    </div>\n' + '                  <div class="actions">\n' + '                    <div class="ui positive right labeled icon button">\n' + '                      Завершить редактирование\n' + '                      <i class="checkmark icon"></i>\n' + '                    </div>\n' + '                  </div>\n' + '                </div>');
      newRow.append(nameCell, usersCell, headersCell, buttonsCell);
      $('#sync-rules').append(newRow);
      $('div.dropdown').dropdown();
    });
    body.on('click', '#save-button', function (e) {
      // Обойдите элементы в цикле
      $("input").each(function (index) {
        $(this).attr('value', $(this).val());
      });
      var data = [];
      var table = $('#sync-rules');
      var rows = table.find('tbody tr');
      rows.each(function () {
        var row = $(this);
        var rowData = {
          id: row.attr('id'),
          name: row.find('td[data-label="name"] input').val(),
          dstUrl: $('.ui.modal[data-id="' + row.attr('id') + '"] input[data-label="dstUrl"]').val(),
          users: row.find('td[data-label="users"] div.dropdown').dropdown('get value'),
          headers: $('.ui.modal[data-id="' + row.attr('id') + '"] textarea').val()
        };
        data.push(rowData);
      });
      $.ajax({
        type: "POST",
        url: globalRootUrl + idUrl + "/save",
        data: {
          rules: data
        },
        success: function success(response) {
          console.log("Успешный запрос", response);

          for (var key in response.ruleSaveResult) {
            if (response.ruleSaveResult.hasOwnProperty(key)) {
              var syncRules = $('#sync-rules tr#' + key);
              var html = syncRules.html();
              syncRules.html(html.replace(new RegExp(key, "g"), response.ruleSaveResult[key]));
              syncRules.attr('id', response.ruleSaveResult[key]);
              $('.ui.modal[data-id="' + key + '"]').attr('data-id', response.ruleSaveResult[key]);
            }
          }
        },
        error: function error(xhr, status, _error) {
          console.error("Ошибка запроса", status, _error);
        }
      });
    });
  },
  showRuleOptions: function showRuleOptions(id) {
    $('.ui.modal[data-id="' + id + '"]').modal({
      closable: true
    }).modal('show');
  },
  removeRule: function removeRule(id) {
    $.ajax({
      type: "POST",
      url: globalRootUrl + idUrl + "/delete",
      data: {
        table: 'ExportRules',
        id: id
      },
      success: function success(response) {
        console.log("Успешный запрос", response);
        $('#sync-rules tr#' + id).remove();
      },
      error: function error(xhr, status, _error2) {
        console.error("Ошибка запроса", status, _error2);
      }
    });
  },

  /**
   * Initializes the date range selector.
   */
  initializeDateRangeSelector: function initializeDateRangeSelector() {
    var _options$ranges;

    var period = ModuleExtendedCDRs.$dateRangeSelector.attr('data-def-value');
    var defPeriod = [moment(), moment()];

    if (period !== '' && period !== undefined) {
      var periods = ModuleExtendedCDRs.getStandardPeriods();

      if (periods[period] !== undefined) {
        defPeriod = periods[period];
      }
    }

    var options = {};
    options.ranges = (_options$ranges = {}, _defineProperty(_options$ranges, globalTranslate.repModuleExtendedCDRs_cdr_cal_Today, [moment(), moment()]), _defineProperty(_options$ranges, globalTranslate.repModuleExtendedCDRs_cdr_cal_Yesterday, [moment().subtract(1, 'days'), moment().subtract(1, 'days')]), _defineProperty(_options$ranges, globalTranslate.repModuleExtendedCDRs_cdr_cal_LastWeek, [moment().subtract(6, 'days'), moment()]), _defineProperty(_options$ranges, globalTranslate.repModuleExtendedCDRs_cdr_cal_Last30Days, [moment().subtract(29, 'days'), moment()]), _defineProperty(_options$ranges, globalTranslate.repModuleExtendedCDRs_cdr_cal_ThisMonth, [moment().startOf('month'), moment().endOf('month')]), _defineProperty(_options$ranges, globalTranslate.repModuleExtendedCDRs_cdr_cal_LastMonth, [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]), _options$ranges);
    options.alwaysShowCalendars = true;
    options.autoUpdateInput = true;
    options.linkedCalendars = true;
    options.maxDate = moment().endOf('month');
    options.locale = {
      format: 'DD/MM/YYYY',
      separator: ' - ',
      applyLabel: globalTranslate.cal_ApplyBtn,
      cancelLabel: globalTranslate.cal_CancelBtn,
      fromLabel: globalTranslate.cal_from,
      toLabel: globalTranslate.cal_to,
      customRangeLabel: globalTranslate.cal_CustomPeriod,
      daysOfWeek: SemanticLocalization.calendarText.days,
      monthNames: SemanticLocalization.calendarText.months,
      firstDay: 1
    };
    options.startDate = defPeriod[0];
    options.endDate = defPeriod[1];
    ModuleExtendedCDRs.$dateRangeSelector.daterangepicker(options, ModuleExtendedCDRs.cbDateRangeSelectorOnSelect);
  },

  /**
   * Handles the date range selector select event.
   * @param {moment.Moment} start - The start date.
   * @param {moment.Moment} end - The end date.
   * @param {string} label - The label.
   */
  cbDateRangeSelectorOnSelect: function cbDateRangeSelectorOnSelect(start, end, label) {
    ModuleExtendedCDRs.$dateRangeSelector.attr('data-start', start.format('YYYY/MM/DD'));
    ModuleExtendedCDRs.$dateRangeSelector.attr('data-end', moment(end.format('YYYYMMDD')).endOf('day').format('YYYY/MM/DD'));
    ModuleExtendedCDRs.$dateRangeSelector.val("".concat(start.format('DD/MM/YYYY'), " - ").concat(end.format('DD/MM/YYYY')));
    ModuleExtendedCDRs.applyFilter();
  },

  /**
   * Applies the filter to the data table.
   */
  applyFilter: function applyFilter() {
    var text = ModuleExtendedCDRs.getSearchText();
    listenedIDs = [];
    ModuleExtendedCDRs.dataTable.search(text).draw();
    ModuleExtendedCDRs.$outgoingEmployeeCalls.DataTable().search(text).draw();
    ModuleExtendedCDRs.$globalSearch.closest('div').addClass('loading');
  },
  getSearchText: function getSearchText() {
    var retStandardPeriod = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : false;
    var disableGlobalSearch = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;
    var dateRangeSelector = '';

    if (retStandardPeriod === true) {
      var periods = ModuleExtendedCDRs.getStandardPeriods();
      $.each(periods, function (index, value) {
        if (ModuleExtendedCDRs.$dateRangeSelector.val() === "".concat(value[0].format('DD/MM/YYYY'), " - ").concat(value[1].format('DD/MM/YYYY'))) {
          dateRangeSelector = index;
        }
      });
    } else {
      dateRangeSelector = ModuleExtendedCDRs.$dateRangeSelector.val();
    }

    var filter = {
      dateRangeSelector: dateRangeSelector,
      globalSearch: ModuleExtendedCDRs.$globalSearch.val(),
      typeCall: $('#typeCall a.item.active').attr('data-tab'),
      additionalFilter: $('#additionalFilter').dropdown('get value').replace(/,/g, ' ')
    };

    if (disableGlobalSearch === true) {
      filter.globalSearch = '';
    }

    return JSON.stringify(filter);
  },
  getStandardPeriods: function getStandardPeriods() {
    return {
      'cal_Today': [moment(), moment()],
      'cal_Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
      'cal_LastWeek': [moment().subtract(6, 'days'), moment()],
      'cal_Last30Days': [moment().subtract(29, 'days'), moment()],
      'cal_ThisMonth': [moment().startOf('month'), moment().endOf('month')],
      'cal_LastMonth': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
    };
  },
  saveSearchSettings: function saveSearchSettings() {
    var search = ModuleExtendedCDRs.getSearchText(true, true);
    $.ajax({
      url: "".concat(globalRootUrl).concat(idUrl, "/saveSearchSettings"),
      type: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      data: {
        'search[value]': search,
        'reportNameID': $('#currentReportNameID').val(),
        'variantId': $('#currentVariantId').val()
      },
      success: function success(response) {
        console.log(response);
        var currentVariantId = $('#currentVariantId').val();

        if (currentVariantId === '') {
          $("#".concat($('#currentReportNameID').val())).attr('data-search-text', encodeURIComponent(search));
        } else {// settings = JSON.parse(decodeURIComponent($(`#${$('#currentReportNameID').val()}`).attr('data-search-text')));
          // TODO
        }
      },
      error: function error(xhr, status, _error3) {
        console.error(_error3);
      }
    });
  },
  startCreateExcelPDF: function startCreateExcelPDF(type) {
    var reportNameID = $('#currentReportNameID').val();
    var encodedSearch = encodeURIComponent(ModuleExtendedCDRs.getSearchText());
    var url = "".concat(window.location.origin, "/pbxcore/api/modules/").concat(className, "/exportHistory?reportNameID=").concat(reportNameID, "&type=").concat(type, "&search=").concat(encodedSearch);
    window.open(url, '_blank');
  },
  getMaxWidth: function getMaxWidth(data, key) {
    // Получаем максимальную длину содержимого в столбце
    var maxLength = key.length; // начинаем с длины заголовка

    data.forEach(function (row) {
      var length = (row[key] || '').toString().length;
      if (length > maxLength) maxLength = length;
    });
    return maxLength;
  },
  startDownload: function startDownload() {
    var startTime = ModuleExtendedCDRs.$dateRangeSelector.attr('data-start');
    var endTime = ModuleExtendedCDRs.$dateRangeSelector.attr('data-end');

    if (startTime === undefined) {
      startTime = moment().format('YYYY-MM-DD');
      endTime = moment().endOf('day').format('YYYY-MM-DD HH:mm:ss');
    }

    var typeRec = 'inner';

    if ($('#allRecord').checkbox('is checked')) {
      typeRec = 'all';
    } else if ($('#outRecord').checkbox('is checked')) {
      typeRec = 'out';
    }

    var numbers = ModuleExtendedCDRs.$globalSearch.val();
    window.open('/pbxcore/api/modules/' + className + '/downloads?start=' + startTime + '&end=' + endTime + "&numbers=" + encodeURIComponent(numbers) + "&type=" + typeRec, '_blank');
  },
  startDownloadHistory: function startDownloadHistory() {
    var startTime = ModuleExtendedCDRs.$dateRangeSelector.attr('data-start');
    var endTime = ModuleExtendedCDRs.$dateRangeSelector.attr('data-end');

    if (startTime === undefined) {
      startTime = moment().format('YYYY-MM-DD');
      endTime = moment().endOf('day').format('YYYY-MM-DD HH:mm:ss');
    }

    var typeRec = 'inner';

    if ($('#allRecord').checkbox('is checked')) {
      typeRec = 'all';
    } else if ($('#outRecord').checkbox('is checked')) {
      typeRec = 'out';
    }

    var numbers = ModuleExtendedCDRs.$globalSearch.val();
    window.open('/pbxcore/api/modules/' + className + '/downloads-history?start=' + startTime + '&end=' + endTime + "&numbers=" + encodeURIComponent(numbers) + "&type=" + typeRec, '_blank');
  },

  /**
   * Подготавливает список выбора
   * @param selected
   * @returns {[]}
   */
  makeDropdownList: function makeDropdownList(selectType, selected) {
    var values = [{
      name: ' --- ',
      value: '',
      selected: '' === selected
    }];
    $('#' + selectType + ' option').each(function (index, obj) {
      values.push({
        name: obj.text,
        value: obj.value,
        selected: selected === obj.value
      });
    });
    return values;
  },

  /**
   * Обработка изменения группы в списке
   */
  changeGroupInList: function changeGroupInList(value, text, choice) {
    var tdInput = $(choice).closest('td').find('input');
    tdInput.attr('data-value', value);
    tdInput.attr('value', value);
    var currentRowId = $(choice).closest('tr').attr('id');
    var tableName = $(choice).closest('table').attr('id').replace('-table', '');

    if (currentRowId !== undefined && tableName !== undefined) {
      window[className].sendChangesToServer(tableName, currentRowId);
    }
  },

  /**
   * Add new Table.
   */
  initTable: function initTable(tableName, options) {
    var columns = [];
    var columnsArray4Sort = [];

    for (var colName in options['cols']) {
      columns.push({
        data: colName
      });
      columnsArray4Sort.push(colName);
    }

    $('#' + tableName).DataTable({
      ajax: {
        url: idUrl + options.ajaxUrl + '?table=' + tableName.replace('-table', ''),
        dataSrc: 'data'
      },
      columns: columns,
      paging: true,
      sDom: 'rtip',
      deferRender: true,
      pageLength: 17,
      infoCallback: function infoCallback(settings, start, end, max, total, pre) {
        return '';
      },
      language: SemanticLocalization.dataTableLocalisation,
      ordering: false,

      /**
       * Builder row presentation
       * @param row
       * @param data
       */
      createdRow: function createdRow(row, data) {
        var cols = $('td', row);
        var headers = $('#' + tableName + ' thead tr th');

        for (var key in data) {
          var index = columnsArray4Sort.indexOf(key);

          if (key === 'rowIcon') {
            cols.eq(index).html('<i class="ui ' + data[key] + ' circle icon"></i>');
          } else if (key === 'delButton') {
            var templateDeleteButton = '<div class="ui small basic icon buttons action-buttons">' + '<a href="' + window[className].deleteRecordAJAXUrl + '/' + data.id + '" data-value = "' + data.DT_RowId + '"' + ' class="ui button delete two-steps-delete popuped" data-content="' + globalTranslate.bt_ToolTipDelete + '">' + '<i class="icon trash red"></i></a></div>';
            cols.eq(index).html(templateDeleteButton);
          } else if (key === 'priority') {
            cols.eq(index).addClass('dragHandle');
            cols.eq(index).html('<i class="ui sort circle icon"></i>'); // Приоритет устанавливаем для строки.

            $(row).attr('m-priority', data[key]);
          } else {
            var template = '<div class="ui transparent fluid input inline-edit">' + '<input colName="' + key + '" class="' + inputClassName + '" type="text" data-value="' + data[key] + '" value="' + data[key] + '"></div>';
            $('td', row).eq(index).html(template);
          }

          if (options['cols'][key] === undefined) {
            continue;
          }

          var additionalClass = options['cols'][key]['class'];

          if (additionalClass !== undefined && additionalClass !== '') {
            headers.eq(index).addClass(additionalClass);
          }

          var header = options['cols'][key]['header'];

          if (header !== undefined && header !== '') {
            headers.eq(index).html(header);
          }

          var selectMetaData = options['cols'][key]['select'];

          if (selectMetaData !== undefined) {
            var newTemplate = $('#template-select').html().replace('PARAM', data[key]);

            var _template = '<input class="' + inputClassName + '" colName="' + key + '" selectType="' + selectMetaData + '" style="display: none;" type="text" data-value="' + data[key] + '" value="' + data[key] + '"></div>';

            cols.eq(index).html(newTemplate + _template);
          }
        }
      },

      /**
       * Draw event - fired once the table has completed a draw.
       */
      drawCallback: function drawCallback(settings) {
        window[className].drowSelectGroup(settings.sTableId);
      }
    });
    var body = $('body'); // Клик по полю. Вход для редактирования значения.

    body.on('focusin', '.' + inputClassName, function (e) {
      $(e.target).transition('glow');
      $(e.target).closest('div').removeClass('transparent').addClass('changed-field');
      $(e.target).attr('readonly', false);
    }); // Отправка формы на сервер по Enter или Tab

    $(document).on('keydown', function (e) {
      var keyCode = e.keyCode || e.which;

      if (keyCode === 13 || keyCode === 9 && $(':focus').hasClass('mikopbx-module-input')) {
        window[className].endEditInput();
      }
    });
    body.on('click', 'a.delete', function (e) {
      e.preventDefault();
      var currentRowId = $(e.target).closest('tr').attr('id');
      var tableName = $(e.target).closest('table').attr('id').replace('-table', '');
      window[className].deleteRow(tableName, currentRowId);
    }); // Добавление новой строки
    // Отправка формы на сервер по уходу с поля ввода

    body.on('focusout', '.' + inputClassName, window[className].endEditInput); // Кнопка "Добавить новую запись"

    $('[id-table = "' + tableName + '"]').on('click', window[className].addNewRow);
  },

  /**
   * Перемещение строки, изменение приоритета.
   */
  cbOnDrop: function cbOnDrop(table, row) {
    var priorityWasChanged = false;
    var priorityData = {};
    $(table).find('tr').each(function (index, obj) {
      var ruleId = $(obj).attr('id');
      var oldPriority = parseInt($(obj).attr('m-priority'), 10);
      var newPriority = obj.rowIndex;

      if (!isNaN(ruleId) && oldPriority !== newPriority) {
        priorityWasChanged = true;
        priorityData[ruleId] = newPriority;
      }
    });

    if (priorityWasChanged) {
      $.api({
        on: 'now',
        url: "".concat(globalRootUrl).concat(idUrl, "/changePriority?table=") + $(table).attr('id').replace('-table', ''),
        method: 'POST',
        data: priorityData
      });
    }
  },

  /**
   * Окончание редактирования поля ввода.
   * Не относится к select.
   * @param e
   */
  endEditInput: function endEditInput(e) {
    var $el = $('.changed-field').closest('tr');
    $el.each(function (index, obj) {
      var currentRowId = $(obj).attr('id');
      var tableName = $(obj).closest('table').attr('id').replace('-table', '');

      if (currentRowId !== undefined && tableName !== undefined) {
        window[className].sendChangesToServer(tableName, currentRowId);
      }
    });
  },

  /**
   * Добавление новой строки в таблицу.
   * @param e
   */
  addNewRow: function addNewRow(e) {
    var idTable = $(e.target).attr('id-table');
    var table = $('#' + idTable);
    e.preventDefault();
    table.find('.dataTables_empty').remove(); // Отправим на запись все что не записано еще

    var $el = table.find('.changed-field').closest('tr');
    $el.each(function (index, obj) {
      var currentRowId = $(obj).attr('id');

      if (currentRowId !== undefined) {
        window[className].sendChangesToServer(currentRowId);
      }
    });
    var id = "new" + Math.floor(Math.random() * Math.floor(500));
    var rowTemplate = '<tr id="' + id + '" role="row" class="even">' + table.find('tr#TEMPLATE').html().replace('TEMPLATE', id) + '</tr>';
    table.find('tbody > tr:first').before(rowTemplate);
    window[className].drowSelectGroup(idTable);
  },

  /**
   * Обновление select элементов.
   * @param tableId
   */
  drowSelectGroup: function drowSelectGroup(tableId) {
    $('#' + tableId).find('tr#TEMPLATE').hide();
    var selestGroup = $('.select-group');
    selestGroup.each(function (index, obj) {
      var selectType = $(obj).closest('td').find('input').attr('selectType');
      $(obj).dropdown({
        values: window[className].makeDropdownList(selectType, $(obj).attr('data-value'))
      });
    });
    selestGroup.dropdown({
      onChange: window[className].changeGroupInList
    });
    $('#' + tableId).tableDnD({
      onDrop: window[className].cbOnDrop,
      onDragClass: 'hoveringRow',
      dragHandle: '.dragHandle'
    });
  },

  /**
   * Удаление строки
   * @param tableName
   * @param id - record id
   */
  deleteRow: function deleteRow(tableName, id) {
    var table = $('#' + tableName + '-table');

    if (id.substr(0, 3) === 'new') {
      table.find('tr#' + id).remove();
      return;
    }

    $.api({
      url: window[className].deleteRecordAJAXUrl + '?id=' + id + '&table=' + tableName,
      on: 'now',
      onSuccess: function onSuccess(response) {
        if (response.success) {
          table.find('tr#' + id).remove();

          if (table.find('tbody > tr').length === 0) {
            table.find('tbody').append('<tr class="odd"></tr>');
          }
        }
      }
    });
  },

  /**
   * Отправка данных на сервер при измении
   */
  sendChangesToServer: function sendChangesToServer(tableName, recordId) {
    var data = {
      'pbx-table-id': tableName,
      'pbx-row-id': recordId
    };
    var notEmpty = false;
    $("tr#" + recordId + ' .' + inputClassName).each(function (index, obj) {
      var colName = $(obj).attr('colName');

      if (colName !== undefined) {
        data[$(obj).attr('colName')] = $(obj).val();

        if ($(obj).val() !== '') {
          notEmpty = true;
        }
      }
    });

    if (notEmpty === false) {
      return;
    }

    $("tr#" + recordId + " .user.circle").removeClass('user circle').addClass('spinner loading');
    $.api({
      url: window[className].saveTableAJAXUrl,
      on: 'now',
      method: 'POST',
      data: data,
      successTest: function successTest(response) {
        return response !== undefined && Object.keys(response).length > 0 && response.success === true;
      },
      onSuccess: function onSuccess(response) {
        if (response.data !== undefined) {
          var rowId = response.data['pbx-row-id'];
          var table = $('#' + response.data['pbx-table-id'] + '-table');
          table.find("tr#" + rowId + " input").attr('readonly', true);
          table.find("tr#" + rowId + " div").removeClass('changed-field loading').addClass('transparent');
          table.find("tr#" + rowId + " .spinner.loading").addClass('user circle').removeClass('spinner loading');

          if (rowId !== response.data['newId']) {
            $("tr#".concat(rowId)).attr('id', response.data['newId']);
          }
        }
      },
      onFailure: function onFailure(response) {
        if (response.message !== undefined) {
          UserMessage.showMultiString(response.message);
        }

        $("tr#" + recordId + " .spinner.loading").addClass('user circle').removeClass('spinner loading');
      },
      onError: function onError(errorMessage, element, xhr) {
        if (xhr.status === 403) {
          window.location = globalRootUrl + "session/index";
        }
      }
    });
  },

  /**
   * Change some form elements classes depends of module status
   */
  checkStatusToggle: function checkStatusToggle() {
    if (window[className].$statusToggle.checkbox('is checked')) {
      window[className].$disabilityFields.removeClass('disabled');
      window[className].$moduleStatus.show();
    } else {
      window[className].$disabilityFields.addClass('disabled');
      window[className].$moduleStatus.hide();
    }
  },

  /**
   * Send command to restart module workers after data changes,
   * Also we can do it on TemplateConf->modelsEventChangeData method
   */
  applyConfigurationChanges: function applyConfigurationChanges() {
    window[className].changeStatus('Updating');
    $.api({
      url: "".concat(Config.pbxUrl, "/pbxcore/api/modules/") + className + "/reload",
      on: 'now',
      successTest: function successTest(response) {
        // test whether a JSON response is valid
        return Object.keys(response).length > 0 && response.result === true;
      },
      onSuccess: function onSuccess() {
        window[className].changeStatus('Connected');
      },
      onFailure: function onFailure() {
        window[className].changeStatus('Disconnected');
      }
    });
  },

  /**
   * We can modify some data before form send
   * @param settings
   * @returns {*}
   */
  cbBeforeSendForm: function cbBeforeSendForm(settings) {
    var result = settings;
    result.data = window[className].$formObj.form('get values');
    return result;
  },

  /**
   * Some actions after forms send
   */
  cbAfterSendForm: function cbAfterSendForm() {
    window[className].applyConfigurationChanges();
  },

  /**
   * Initialize form parameters
   */
  initializeForm: function initializeForm() {
    Form.$formObj = window[className].$formObj;
    Form.url = "".concat(globalRootUrl).concat(idUrl, "/save");
    Form.validateRules = window[className].validateRules;
    Form.cbBeforeSendForm = window[className].cbBeforeSendForm;
    Form.cbAfterSendForm = window[className].cbAfterSendForm;
    Form.initialize();
  },

  /**
   * Update the module state on form label
   * @param status
   */
  changeStatus: function changeStatus(status) {
    switch (status) {
      case 'Connected':
        window[className].$moduleStatus.removeClass('grey').removeClass('red').addClass('green');
        window[className].$moduleStatus.html(globalTranslate.module_export_recordsConnected);
        break;

      case 'Disconnected':
        window[className].$moduleStatus.removeClass('green').removeClass('red').addClass('grey');
        window[className].$moduleStatus.html(globalTranslate.module_export_recordsDisconnected);
        break;

      case 'Updating':
        window[className].$moduleStatus.removeClass('green').removeClass('red').addClass('grey');
        window[className].$moduleStatus.html("<i class=\"spinner loading icon\"></i>".concat(globalTranslate.module_export_recordsUpdateStatus));
        break;

      default:
        window[className].$moduleStatus.removeClass('green').removeClass('red').addClass('grey');
        window[className].$moduleStatus.html(globalTranslate.module_export_recordsDisconnected);
        break;
    }
  }
};
$(document).ready(function () {
  window[className].initialize();
});
//# sourceMappingURL=module-export-records-index.js.map