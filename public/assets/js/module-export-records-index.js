"use strict";

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

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
/* global globalRootUrl, globalTranslate, Form, Config */

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
      ModuleExtendedCDRs.startCreateExcel();
    });
    $('#saveSearchSettings').on('click', function (e) {
      ModuleExtendedCDRs.saveSearchSettings();
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
    ModuleExtendedCDRs.$cdrTable.dataTable({
      search: {
        search: ModuleExtendedCDRs.getSearchText()
      },
      serverSide: true,
      processing: true,
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
        ;
        $('td', row).eq(2).html(data[1]).attr('data-phone', data[1]).addClass('need-update').addClass('right aligned');
        ;
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
      drawCallback: function drawCallback() {
        Extensions.updatePhonesRepresent('need-update');
      },
      language: SemanticLocalization.dataTableLocalisation,
      ordering: false
    });
    ModuleExtendedCDRs.dataTable = ModuleExtendedCDRs.$cdrTable.DataTable();
    ModuleExtendedCDRs.dataTable.on('draw', function () {
      ModuleExtendedCDRs.$globalSearch.closest('div').removeClass('loading');
    });
    ModuleExtendedCDRs.$cdrTable.on('click', 'tr.negative', function (e) {
      var filter = $(e.target).attr('data-phone');

      if (filter !== undefined && filter !== '') {
        ModuleExtendedCDRs.$globalSearch.val(filter);
        ModuleExtendedCDRs.applyFilter();
        return;
      }

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
      }

      var filter = $(e.target).attr('data-phone');

      if (filter !== undefined && filter !== '') {
        ModuleExtendedCDRs.$globalSearch.val(filter);
        ModuleExtendedCDRs.applyFilter();
        return;
      }

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
      }
    });
    window[className].updateSyncState();
    setInterval(window[className].updateSyncState, 5000);
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
        var recordFileName = "record_".concat(record.src_num, "_to_").concat(record.dst_num, "_from_").concat(data[0]);
        recordFileName.replace(/[^\w\s!?]/g, '');
        recordFileName = encodeURIComponent(recordFileName);
        var recordFileUri = encodeURIComponent(record.recordingfile);
        srcAudio = "/pbxcore/api/cdr/v2/playback?view=".concat(recordFileUri);
        srcDownloadAudio = "/pbxcore/api/cdr/v2/playback?view=".concat(recordFileUri, "&download=1&filename=").concat(recordFileName, ".mp3");
      }

      htmlPlayer += "\n\t\t\t<tr id=\"".concat(record.id, "\" data-row-id=\"").concat(id, "-detailed\" class=\"warning detailed odd shown\" role=\"row\">\n\t\t\t\t<td></td>\n\t\t\t\t<td class=\"right aligned\">").concat(record.start, "</td>\n\t\t\t\t<td data-phone=\"").concat(record.src_num, "\" class=\"right aligned need-update\">").concat(record.src_num, "</td>\n\t\t\t   \t<td data-phone=\"").concat(record.dst_num, "\" class=\"left aligned need-update\">").concat(record.dst_num, "</td>\n\t\t\t\t<td class=\"right aligned\">\t\t\t\n\t\t\t\t</td>\n\t\t\t\t<td class=\"right aligned\">").concat(record.waitTime, "</td>\n\t\t\t\t<td class=\"right aligned\">\n\t\t\t\t\t<i class=\"ui icon play\"></i>\n\t\t\t\t\t<audio preload=\"metadata\" id=\"audio-player-").concat(record.id, "\" src=\"").concat(srcAudio, "\"></audio>\n\t\t\t\t\t").concat(record.billsec, "\n\t\t\t\t\t<i class=\"ui icon download\" data-value=\"").concat(srcDownloadAudio, "\"></i>\n\t\t\t\t</td>\n\t\t\t\t<td class=\"right aligned\" data-state-index=\"").concat(record.stateCallIndex, "\">").concat(record.stateCall, "</td>\n\t\t\t</tr>");
    });
    return htmlPlayer;
  },
  calculatePageLength: function calculatePageLength() {
    // Calculate row height
    var rowHeight = ModuleExtendedCDRs.$cdrTable.find('tbody > tr').first().outerHeight(); // Calculate window height and available space for table

    var windowHeight = window.innerHeight;
    var headerFooterHeight = 400 + 50; // Estimate height for header, footer, and other elements
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
    ModuleExtendedCDRs.dataTable.search(text).draw();
    ModuleExtendedCDRs.$globalSearch.closest('div').addClass('loading');
  },
  getSearchText: function getSearchText() {
    var filter = {
      dateRangeSelector: ModuleExtendedCDRs.$dateRangeSelector.val(),
      globalSearch: ModuleExtendedCDRs.$globalSearch.val(),
      typeCall: $('#typeCall a.item.active').attr('data-tab'),
      additionalFilter: $('#additionalFilter').dropdown('get value').replace(/,/g, ' ')
    };
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
    var periods = ModuleExtendedCDRs.getStandardPeriods();
    var dateRangeSelector = '';
    $.each(periods, function (index, value) {
      if (ModuleExtendedCDRs.$dateRangeSelector.val() === "".concat(value[0].format('DD/MM/YYYY'), " - ").concat(value[1].format('DD/MM/YYYY'))) {
        dateRangeSelector = index;
      }
    });
    var settings = {
      'additionalFilter': $('#additionalFilter').dropdown('get value').replace(/,/g, ' '),
      'dateRangeSelector': dateRangeSelector
    };
    $.ajax({
      url: "".concat(globalRootUrl).concat(idUrl, "/saveSearchSettings"),
      type: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      data: {
        'search[value]': JSON.stringify(settings)
      },
      success: function success(response) {
        console.log(response);
      },
      error: function error(xhr, status, _error3) {
        console.error(_error3);
      }
    });
  },
  startCreateExcel: function startCreateExcel() {
    var text = ModuleExtendedCDRs.getSearchText();
    $.ajax({
      url: "".concat(globalRootUrl).concat(idUrl, "/getHistory"),
      type: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      data: {
        'search[value]': text
      },
      success: function success(response) {
        var flattenedData = [{
          start: '',
          src_num: '',
          dst_num: '',
          billsec: '',
          stateCall: '',
          typeCall: '',
          line: '',
          waitTime: ''
        }];
        $.each(response.data, function (index, item) {
          var baseRecord = {
            start: item['0'],
            src_num: item['1'],
            dst_num: item['2'],
            billsec: item['3'],
            stateCall: item['stateCall'],
            typeCall: item['typeCallDesc'],
            line: item['line'],
            waitTime: item['waitTime']
          }; // Добавляем основной элемент

          flattenedData.push(baseRecord); // Если есть вложенные данные, добавляем их сразу после основного элемента

          if (item['4'] && item['4'].length > 0) {
            $.each(item['4'], function (i, nestedItem) {
              // Проверяем, совпадают ли значения start, src_num и dst_num с основной записью
              if (nestedItem.start !== item['0'] || nestedItem.src_num !== item['1'] || nestedItem.dst_num !== item['2']) {
                flattenedData.push({
                  start: nestedItem.start || item['0'],
                  src_num: nestedItem.src_num || item['1'],
                  dst_num: nestedItem.dst_num || item['2'],
                  billsec: nestedItem.billsec || item['3'],
                  stateCall: nestedItem.stateCall,
                  typeCall: item['typeCallDesc'],
                  line: item['line'],
                  waitTime: nestedItem.waitTime // Другие свойства можно добавить здесь

                });
              }
            });
          }
        });
        var columns = ["typeCall", "start", "src_num", "dst_num", "line", "waitTime", "billsec", "stateCall"];
        var worksheet = XLSX.utils.json_to_sheet(flattenedData, {
          header: columns,
          skipHeader: true // Пропускаем автоматическое создание заголовков из ключей объекта

        });
        XLSX.utils.sheet_add_aoa(worksheet, [[globalTranslate.repModuleExtendedCDRs_cdr_ColumnTypeState, globalTranslate.cdr_ColumnDate, globalTranslate.cdr_ColumnFrom, globalTranslate.cdr_ColumnTo, globalTranslate.repModuleExtendedCDRs_cdr_ColumnLine, globalTranslate.repModuleExtendedCDRs_cdr_ColumnWaitTime, globalTranslate.cdr_ColumnDuration, globalTranslate.repModuleExtendedCDRs_cdr_ColumnCallState]], {
          origin: "A1"
        });
        worksheet['!cols'] = columns.map(function (col) {
          return {
            wch: 8 + ModuleExtendedCDRs.getMaxWidth(flattenedData, col)
          };
        });
        var workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "cdr");
        XLSX.writeFile(workbook, "history.xlsx");
      },
      error: function error(xhr, status, _error4) {
        console.error(_error4);
      }
    });
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
//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJzb3VyY2VzIjpbInNyYy9tb2R1bGUtZXhwb3J0LXJlY29yZHMtaW5kZXguanMiXSwibmFtZXMiOlsiaWRVcmwiLCJpZEZvcm0iLCJjbGFzc05hbWUiLCJpbnB1dENsYXNzTmFtZSIsIk1vZHVsZUV4dGVuZGVkQ0RScyIsIiRmb3JtT2JqIiwiJCIsIiRjaGVja0JveGVzIiwiJGRyb3BEb3ducyIsInNhdmVUYWJsZUFKQVhVcmwiLCJnbG9iYWxSb290VXJsIiwiZGVsZXRlUmVjb3JkQUpBWFVybCIsIiRkaXNhYmlsaXR5RmllbGRzIiwiJHN0YXR1c1RvZ2dsZSIsIiRtb2R1bGVTdGF0dXMiLCIkY2RyVGFibGUiLCIkZ2xvYmFsU2VhcmNoIiwiJGRhdGVSYW5nZVNlbGVjdG9yIiwiZGF0YVRhYmxlIiwicGxheWVycyIsInZhbGlkYXRlUnVsZXMiLCJpbml0aWFsaXplIiwiaW5pdGlhbGl6ZURhdGVSYW5nZVNlbGVjdG9yIiwid2luZG93IiwiY2hlY2tib3giLCJkcm9wZG93biIsIm9uQ2hhbmdlIiwiYXBwbHlGaWx0ZXIiLCJhZGRFdmVudExpc3RlbmVyIiwiY2hlY2tTdGF0dXNUb2dnbGUiLCJpbml0aWFsaXplRm9ybSIsInRhYiIsIm9uIiwiZSIsInN0YXJ0Q3JlYXRlRXhjZWwiLCJzYXZlU2VhcmNoU2V0dGluZ3MiLCJrZXlDb2RlIiwidmFsIiwibGVuZ3RoIiwia2V5ZG93biIsImV2ZW50IiwicHJldmVudERlZmF1bHQiLCJzZWFyY2giLCJnZXRTZWFyY2hUZXh0Iiwic2VydmVyU2lkZSIsInByb2Nlc3NpbmciLCJjb2x1bW5EZWZzIiwiZGVmYXVsdENvbnRlbnQiLCJ0YXJnZXRzIiwiYWpheCIsInVybCIsInR5cGUiLCJkYXRhU3JjIiwianNvbiIsImh0bWwiLCJyZWNvcmRzRmlsdGVyZWQiLCJyZWNvcmRzSW5jb21pbmciLCJyZWNvcmRzTWlzc2VkIiwicmVjb3Jkc091dGdvaW5nIiwidHlwZUNhbGwiLCJhdHRyIiwiZGF0YSIsInBhZ2luZyIsInNEb20iLCJkZWZlclJlbmRlciIsInBhZ2VMZW5ndGgiLCJjYWxjdWxhdGVQYWdlTGVuZ3RoIiwiY3JlYXRlZFJvdyIsInJvdyIsImRldGFpbGVkSWNvbiIsIkRUX1Jvd0NsYXNzIiwiaW5kZXhPZiIsImVxIiwiYWRkQ2xhc3MiLCJkdXJhdGlvbiIsImlkcyIsImxpbmVUZXh0IiwibGluZSIsImRpZCIsIndhaXRUaW1lIiwic3RhdGVDYWxsIiwiZHJhd0NhbGxiYWNrIiwiRXh0ZW5zaW9ucyIsInVwZGF0ZVBob25lc1JlcHJlc2VudCIsImxhbmd1YWdlIiwiU2VtYW50aWNMb2NhbGl6YXRpb24iLCJkYXRhVGFibGVMb2NhbGlzYXRpb24iLCJvcmRlcmluZyIsIkRhdGFUYWJsZSIsImNsb3Nlc3QiLCJyZW1vdmVDbGFzcyIsImZpbHRlciIsInRhcmdldCIsInVuZGVmaW5lZCIsImxvY2F0aW9uIiwidHIiLCJoYXNDbGFzcyIsInJlbW92ZSIsImFmdGVyIiwic2hvd1JlY29yZHMiLCJlYWNoIiwiaW5kZXgiLCJwbGF5ZXJSb3ciLCJpZCIsIkNEUlBsYXllciIsInVwZGF0ZVN5bmNTdGF0ZSIsInNldEludGVydmFsIiwiZGl2UHJvZ3Jlc3MiLCJtZXRob2QiLCJzdWNjZXNzIiwicmVzcG9uc2UiLCJzdGF0ZURhdGEiLCJsYXN0SWQiLCJub3dJZCIsInNob3ciLCJoaWRlIiwicHJvZ3Jlc3MiLCJ0b3RhbCIsInZhbHVlIiwidGV4dCIsImFjdGl2ZSIsImdsb2JhbFRyYW5zbGF0ZSIsInJlcE1vZHVsZUV4dGVuZGVkQ0RSc19zeW5jU3RhdGUiLCJlcnJvciIsImpxWEhSIiwidGV4dFN0YXR1cyIsImVycm9yVGhyb3duIiwiY29uc29sZSIsImh0bWxQbGF5ZXIiLCJmb3JFYWNoIiwicmVjb3JkIiwiaSIsInNyY0F1ZGlvIiwic3JjRG93bmxvYWRBdWRpbyIsInJlY29yZGluZ2ZpbGUiLCJyZWNvcmRGaWxlTmFtZSIsInNyY19udW0iLCJkc3RfbnVtIiwicmVwbGFjZSIsImVuY29kZVVSSUNvbXBvbmVudCIsInJlY29yZEZpbGVVcmkiLCJzdGFydCIsImJpbGxzZWMiLCJzdGF0ZUNhbGxJbmRleCIsInJvd0hlaWdodCIsImZpbmQiLCJmaXJzdCIsIm91dGVySGVpZ2h0Iiwid2luZG93SGVpZ2h0IiwiaW5uZXJIZWlnaHQiLCJoZWFkZXJGb290ZXJIZWlnaHQiLCJNYXRoIiwibWF4IiwiZmxvb3IiLCJhZGRpdGlvbmFsRXhwb3J0RnVuY3Rpb25zIiwiYm9keSIsIkRhdGUiLCJub3ciLCJuZXdSb3ciLCJuYW1lQ2VsbCIsInVzZXJzQ2VsbCIsImJ1dHRvbnNDZWxsIiwiaGVhZGVyc0NlbGwiLCJhcHBlbmQiLCJ0YWJsZSIsInJvd3MiLCJyb3dEYXRhIiwibmFtZSIsImRzdFVybCIsInVzZXJzIiwiaGVhZGVycyIsInB1c2giLCJydWxlcyIsImxvZyIsImtleSIsInJ1bGVTYXZlUmVzdWx0IiwiaGFzT3duUHJvcGVydHkiLCJzeW5jUnVsZXMiLCJSZWdFeHAiLCJ4aHIiLCJzdGF0dXMiLCJzaG93UnVsZU9wdGlvbnMiLCJtb2RhbCIsImNsb3NhYmxlIiwicmVtb3ZlUnVsZSIsInBlcmlvZCIsImRlZlBlcmlvZCIsIm1vbWVudCIsInBlcmlvZHMiLCJnZXRTdGFuZGFyZFBlcmlvZHMiLCJvcHRpb25zIiwicmFuZ2VzIiwicmVwTW9kdWxlRXh0ZW5kZWRDRFJzX2Nkcl9jYWxfVG9kYXkiLCJyZXBNb2R1bGVFeHRlbmRlZENEUnNfY2RyX2NhbF9ZZXN0ZXJkYXkiLCJzdWJ0cmFjdCIsInJlcE1vZHVsZUV4dGVuZGVkQ0RSc19jZHJfY2FsX0xhc3RXZWVrIiwicmVwTW9kdWxlRXh0ZW5kZWRDRFJzX2Nkcl9jYWxfTGFzdDMwRGF5cyIsInJlcE1vZHVsZUV4dGVuZGVkQ0RSc19jZHJfY2FsX1RoaXNNb250aCIsInN0YXJ0T2YiLCJlbmRPZiIsInJlcE1vZHVsZUV4dGVuZGVkQ0RSc19jZHJfY2FsX0xhc3RNb250aCIsImFsd2F5c1Nob3dDYWxlbmRhcnMiLCJhdXRvVXBkYXRlSW5wdXQiLCJsaW5rZWRDYWxlbmRhcnMiLCJtYXhEYXRlIiwibG9jYWxlIiwiZm9ybWF0Iiwic2VwYXJhdG9yIiwiYXBwbHlMYWJlbCIsImNhbF9BcHBseUJ0biIsImNhbmNlbExhYmVsIiwiY2FsX0NhbmNlbEJ0biIsImZyb21MYWJlbCIsImNhbF9mcm9tIiwidG9MYWJlbCIsImNhbF90byIsImN1c3RvbVJhbmdlTGFiZWwiLCJjYWxfQ3VzdG9tUGVyaW9kIiwiZGF5c09mV2VlayIsImNhbGVuZGFyVGV4dCIsImRheXMiLCJtb250aE5hbWVzIiwibW9udGhzIiwiZmlyc3REYXkiLCJzdGFydERhdGUiLCJlbmREYXRlIiwiZGF0ZXJhbmdlcGlja2VyIiwiY2JEYXRlUmFuZ2VTZWxlY3Rvck9uU2VsZWN0IiwiZW5kIiwibGFiZWwiLCJkcmF3IiwiZGF0ZVJhbmdlU2VsZWN0b3IiLCJnbG9iYWxTZWFyY2giLCJhZGRpdGlvbmFsRmlsdGVyIiwiSlNPTiIsInN0cmluZ2lmeSIsInNldHRpbmdzIiwiZmxhdHRlbmVkRGF0YSIsIml0ZW0iLCJiYXNlUmVjb3JkIiwibmVzdGVkSXRlbSIsImNvbHVtbnMiLCJ3b3Jrc2hlZXQiLCJYTFNYIiwidXRpbHMiLCJqc29uX3RvX3NoZWV0IiwiaGVhZGVyIiwic2tpcEhlYWRlciIsInNoZWV0X2FkZF9hb2EiLCJyZXBNb2R1bGVFeHRlbmRlZENEUnNfY2RyX0NvbHVtblR5cGVTdGF0ZSIsImNkcl9Db2x1bW5EYXRlIiwiY2RyX0NvbHVtbkZyb20iLCJjZHJfQ29sdW1uVG8iLCJyZXBNb2R1bGVFeHRlbmRlZENEUnNfY2RyX0NvbHVtbkxpbmUiLCJyZXBNb2R1bGVFeHRlbmRlZENEUnNfY2RyX0NvbHVtbldhaXRUaW1lIiwiY2RyX0NvbHVtbkR1cmF0aW9uIiwicmVwTW9kdWxlRXh0ZW5kZWRDRFJzX2Nkcl9Db2x1bW5DYWxsU3RhdGUiLCJvcmlnaW4iLCJtYXAiLCJjb2wiLCJ3Y2giLCJnZXRNYXhXaWR0aCIsIndvcmtib29rIiwiYm9va19uZXciLCJib29rX2FwcGVuZF9zaGVldCIsIndyaXRlRmlsZSIsIm1heExlbmd0aCIsInRvU3RyaW5nIiwic3RhcnREb3dubG9hZCIsInN0YXJ0VGltZSIsImVuZFRpbWUiLCJ0eXBlUmVjIiwibnVtYmVycyIsIm9wZW4iLCJzdGFydERvd25sb2FkSGlzdG9yeSIsIm1ha2VEcm9wZG93bkxpc3QiLCJzZWxlY3RUeXBlIiwic2VsZWN0ZWQiLCJ2YWx1ZXMiLCJvYmoiLCJjaGFuZ2VHcm91cEluTGlzdCIsImNob2ljZSIsInRkSW5wdXQiLCJjdXJyZW50Um93SWQiLCJ0YWJsZU5hbWUiLCJzZW5kQ2hhbmdlc1RvU2VydmVyIiwiaW5pdFRhYmxlIiwiY29sdW1uc0FycmF5NFNvcnQiLCJjb2xOYW1lIiwiYWpheFVybCIsImluZm9DYWxsYmFjayIsInByZSIsImNvbHMiLCJ0ZW1wbGF0ZURlbGV0ZUJ1dHRvbiIsIkRUX1Jvd0lkIiwiYnRfVG9vbFRpcERlbGV0ZSIsInRlbXBsYXRlIiwiYWRkaXRpb25hbENsYXNzIiwic2VsZWN0TWV0YURhdGEiLCJuZXdUZW1wbGF0ZSIsImRyb3dTZWxlY3RHcm91cCIsInNUYWJsZUlkIiwidHJhbnNpdGlvbiIsImRvY3VtZW50Iiwid2hpY2giLCJlbmRFZGl0SW5wdXQiLCJkZWxldGVSb3ciLCJhZGROZXdSb3ciLCJjYk9uRHJvcCIsInByaW9yaXR5V2FzQ2hhbmdlZCIsInByaW9yaXR5RGF0YSIsInJ1bGVJZCIsIm9sZFByaW9yaXR5IiwicGFyc2VJbnQiLCJuZXdQcmlvcml0eSIsInJvd0luZGV4IiwiaXNOYU4iLCJhcGkiLCIkZWwiLCJpZFRhYmxlIiwicmFuZG9tIiwicm93VGVtcGxhdGUiLCJiZWZvcmUiLCJ0YWJsZUlkIiwic2VsZXN0R3JvdXAiLCJ0YWJsZURuRCIsIm9uRHJvcCIsIm9uRHJhZ0NsYXNzIiwiZHJhZ0hhbmRsZSIsInN1YnN0ciIsIm9uU3VjY2VzcyIsInJlY29yZElkIiwibm90RW1wdHkiLCJzdWNjZXNzVGVzdCIsIk9iamVjdCIsImtleXMiLCJyb3dJZCIsIm9uRmFpbHVyZSIsIm1lc3NhZ2UiLCJVc2VyTWVzc2FnZSIsInNob3dNdWx0aVN0cmluZyIsIm9uRXJyb3IiLCJlcnJvck1lc3NhZ2UiLCJlbGVtZW50IiwiYXBwbHlDb25maWd1cmF0aW9uQ2hhbmdlcyIsImNoYW5nZVN0YXR1cyIsIkNvbmZpZyIsInBieFVybCIsInJlc3VsdCIsImNiQmVmb3JlU2VuZEZvcm0iLCJmb3JtIiwiY2JBZnRlclNlbmRGb3JtIiwiRm9ybSIsIm1vZHVsZV9leHBvcnRfcmVjb3Jkc0Nvbm5lY3RlZCIsIm1vZHVsZV9leHBvcnRfcmVjb3Jkc0Rpc2Nvbm5lY3RlZCIsIm1vZHVsZV9leHBvcnRfcmVjb3Jkc1VwZGF0ZVN0YXR1cyIsInJlYWR5Il0sIm1hcHBpbmdzIjoiOzs7O0FBQUE7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQSxJQUFNQSxLQUFLLEdBQU8sd0JBQWxCO0FBQ0EsSUFBTUMsTUFBTSxHQUFNLDBCQUFsQjtBQUNBLElBQU1DLFNBQVMsR0FBRyxvQkFBbEI7QUFDQSxJQUFNQyxjQUFjLEdBQUcsc0JBQXZCO0FBRUE7O0FBQ0EsSUFBTUMsa0JBQWtCLEdBQUc7QUFDMUJDLEVBQUFBLFFBQVEsRUFBRUMsQ0FBQyxDQUFDLE1BQUlMLE1BQUwsQ0FEZTtBQUUxQk0sRUFBQUEsV0FBVyxFQUFFRCxDQUFDLENBQUMsTUFBSUwsTUFBSixHQUFXLGVBQVosQ0FGWTtBQUcxQk8sRUFBQUEsVUFBVSxFQUFFRixDQUFDLENBQUMsTUFBSUwsTUFBSixHQUFXLGVBQVosQ0FIYTtBQUkxQlEsRUFBQUEsZ0JBQWdCLEVBQUVDLGFBQWEsR0FBR1YsS0FBaEIsR0FBd0IsZ0JBSmhCO0FBSzFCVyxFQUFBQSxtQkFBbUIsRUFBRUQsYUFBYSxHQUFHVixLQUFoQixHQUF3QixTQUxuQjtBQU0xQlksRUFBQUEsaUJBQWlCLEVBQUVOLENBQUMsQ0FBQyxNQUFJTCxNQUFKLEdBQVcsZUFBWixDQU5NO0FBTzFCWSxFQUFBQSxhQUFhLEVBQUVQLENBQUMsQ0FBQyx1QkFBRCxDQVBVO0FBUTFCUSxFQUFBQSxhQUFhLEVBQUVSLENBQUMsQ0FBQyxTQUFELENBUlU7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QUdXLE1BSFg7QUFJQWxELFFBQUFBLE1BQU0sQ0FBQ3JCLFNBQUQsQ0FBTixDQUFrQlksYUFBbEIsQ0FBZ0N3QyxJQUFoQyxDQUFxQ21FLGVBQWUsQ0FBQ3lPLGlDQUFyRDtBQUNBOztBQUNELFdBQUssVUFBTDtBQUNDM1UsUUFBQUEsTUFBTSxDQUFDckIsU0FBRCxDQUFOLENBQWtCWSxhQUFsQixDQUNFNEUsV0FERixDQUNjLE9BRGQsRUFFRUEsV0FGRixDQUVjLEtBRmQsRUFHRWpCLFFBSEYsQ0FHVyxNQUhYO0FBSUFsRCxRQUFBQSxNQUFNLENBQUNyQixTQUFELENBQU4sQ0FBa0JZLGFBQWxCLENBQWdDd0MsSUFBaEMsaURBQTRFbUUsZUFBZSxDQUFDME8saUNBQTVGO0FBQ0E7O0FBQ0Q7QUFDQzVVLFFBQUFBLE1BQU0sQ0FBQ3JCLFNBQUQsQ0FBTixDQUFrQlksYUFBbEIsQ0FDRTRFLFdBREYsQ0FDYyxPQURkLEVBRUVBLFdBRkYsQ0FFYyxLQUZkLEVBR0VqQixRQUhGLENBR1csTUFIWDtBQUlBbEQsUUFBQUEsTUFBTSxDQUFDckIsU0FBRCxDQUFOLENBQWtCWSxhQUFsQixDQUFnQ3dDLElBQWhDLENBQXFDbUUsZUFBZSxDQUFDeU8saUNBQXJEO0FBQ0E7QUE1QkY7QUE4QkE7QUF6a0N5QixDQUEzQjtBQTRrQ0E1VixDQUFDLENBQUN5UyxRQUFELENBQUQsQ0FBWXFELEtBQVosQ0FBa0IsWUFBTTtBQUN2QjdVLEVBQUFBLE1BQU0sQ0FBQ3JCLFNBQUQsQ0FBTixDQUFrQm1CLFVBQWxCO0FBQ0EsQ0FGRCIsInNvdXJjZXNDb250ZW50IjpbIi8qXG4gKiBDb3B5cmlnaHQgKEMpIE1JS08gTExDIC0gQWxsIFJpZ2h0cyBSZXNlcnZlZFxuICogVW5hdXRob3JpemVkIGNvcHlpbmcgb2YgdGhpcyBmaWxlLCB2aWEgYW55IG1lZGl1bSBpcyBzdHJpY3RseSBwcm9oaWJpdGVkXG4gKiBQcm9wcmlldGFyeSBhbmQgY29uZmlkZW50aWFsXG4gKiBXcml0dGVuIGJ5IE5pa29sYXkgQmVrZXRvdiwgMTEgMjAxOFxuICpcbiAqL1xuY29uc3QgaWRVcmwgICAgID0gJ21vZHVsZS1leHRlbmRlZC1jLWQtcnMnO1xuY29uc3QgaWRGb3JtICAgID0gJ21vZHVsZS1leHRlbmRlZC1jZHItZm9ybSc7XG5jb25zdCBjbGFzc05hbWUgPSAnTW9kdWxlRXh0ZW5kZWRDRFJzJztcbmNvbnN0IGlucHV0Q2xhc3NOYW1lID0gJ21pa29wYngtbW9kdWxlLWlucHV0JztcblxuLyogZ2xvYmFsIGdsb2JhbFJvb3RVcmwsIGdsb2JhbFRyYW5zbGF0ZSwgRm9ybSwgQ29uZmlnICovXG5jb25zdCBNb2R1bGVFeHRlbmRlZENEUnMgPSB7XG5cdCRmb3JtT2JqOiAkKCcjJytpZEZvcm0pLFxuXHQkY2hlY2tCb3hlczogJCgnIycraWRGb3JtKycgLnVpLmNoZWNrYm94JyksXG5cdCRkcm9wRG93bnM6ICQoJyMnK2lkRm9ybSsnIC51aS5kcm9wZG93bicpLFxuXHRzYXZlVGFibGVBSkFYVXJsOiBnbG9iYWxSb290VXJsICsgaWRVcmwgKyBcIi9zYXZlVGFibGVEYXRhXCIsXG5cdGRlbGV0ZVJlY29yZEFKQVhVcmw6IGdsb2JhbFJvb3RVcmwgKyBpZFVybCArIFwiL2RlbGV0ZVwiLFxuXHQkZGlzYWJpbGl0eUZpZWxkczogJCgnIycraWRGb3JtKycgIC5kaXNhYmlsaXR5JyksXG5cdCRzdGF0dXNUb2dnbGU6ICQoJyNtb2R1bGUtc3RhdHVzLXRvZ2dsZScpLFxuXHQkbW9kdWxlU3RhdHVzOiAkKCcjc3RhdHVzJyksXG5cblx0LyoqXG5cdCAqIFRoZSBjYWxsIGRldGFpbCByZWNvcmRzIHRhYmxlIGVsZW1lbnQuXG5cdCAqIEB0eXBlIHtqUXVlcnl9XG5cdCAqL1xuXHQkY2RyVGFibGU6ICQoJyNjZHItdGFibGUnKSxcblxuXHQvKipcblx0ICogVGhlIGdsb2JhbCBzZWFyY2ggaW5wdXQgZWxlbWVudC5cblx0ICogQHR5cGUge2pRdWVyeX1cblx0ICovXG5cdCRnbG9iYWxTZWFyY2g6ICQoJyNnbG9iYWxzZWFyY2gnKSxcblxuXHQvKipcblx0ICogVGhlIGRhdGUgcmFuZ2Ugc2VsZWN0b3IgZWxlbWVudC5cblx0ICogQHR5cGUge2pRdWVyeX1cblx0ICovXG5cdCRkYXRlUmFuZ2VTZWxlY3RvcjogJCgnI2RhdGUtcmFuZ2Utc2VsZWN0b3InKSxcblxuXHQvKipcblx0ICogVGhlIGRhdGEgdGFibGUgb2JqZWN0LlxuXHQgKiBAdHlwZSB7T2JqZWN0fVxuXHQgKi9cblx0ZGF0YVRhYmxlOiB7fSxcblxuXHQvKipcblx0ICogQW4gYXJyYXkgb2YgcGxheWVycy5cblx0ICogQHR5cGUge0FycmF5fVxuXHQgKi9cblx0cGxheWVyczogW10sXG5cblx0LyoqXG5cdCAqIEZpZWxkIHZhbGlkYXRpb24gcnVsZXNcblx0ICogaHR0cHM6Ly9zZW1hbnRpYy11aS5jb20vYmVoYXZpb3JzL2Zvcm0uaHRtbFxuXHQgKi9cblx0dmFsaWRhdGVSdWxlczoge1xuXHR9LFxuXHQvKipcblx0ICogT24gcGFnZSBsb2FkIHdlIGluaXQgc29tZSBTZW1hbnRpYyBVSSBsaWJyYXJ5XG5cdCAqL1xuXHRpbml0aWFsaXplKCkge1xuXHRcdE1vZHVsZUV4dGVuZGVkQ0RScy5pbml0aWFsaXplRGF0ZVJhbmdlU2VsZWN0b3IoKTtcblx0XHQvLyDQuNC90LjRhtC40LDQu9C40LfQuNGA0YPQtdC8INGH0LXQutCx0L7QutGB0Ysg0Lgg0LLRi9C/0L7QtNCw0Y7RidC40LUg0LzQtdC90Y7RiNC60Lhcblx0XHR3aW5kb3dbY2xhc3NOYW1lXS4kY2hlY2tCb3hlcy5jaGVja2JveCgpO1xuXHRcdHdpbmRvd1tjbGFzc05hbWVdLiRkcm9wRG93bnMuZHJvcGRvd24oe29uQ2hhbmdlOiBNb2R1bGVFeHRlbmRlZENEUnMuYXBwbHlGaWx0ZXJ9KTtcblxuXHRcdHdpbmRvdy5hZGRFdmVudExpc3RlbmVyKCdNb2R1bGVTdGF0dXNDaGFuZ2VkJywgd2luZG93W2NsYXNzTmFtZV0uY2hlY2tTdGF0dXNUb2dnbGUpO1xuXHRcdHdpbmRvd1tjbGFzc05hbWVdLmluaXRpYWxpemVGb3JtKCk7XG5cblx0XHQkKCcubWVudSAuaXRlbScpLnRhYigpO1xuXHRcdCQoJyN0eXBlQ2FsbC5tZW51IGEuaXRlbScpLm9uKCdjbGljaycsIGZ1bmN0aW9uIChlKSB7XG5cdFx0XHRNb2R1bGVFeHRlbmRlZENEUnMuYXBwbHlGaWx0ZXIoKTtcblx0XHR9KTtcblx0XHQkKCcjY3JlYXRlRXhjZWxCdXR0b24nKS5vbignY2xpY2snLCBmdW5jdGlvbiAoZSkge1xuXHRcdFx0TW9kdWxlRXh0ZW5kZWRDRFJzLnN0YXJ0Q3JlYXRlRXhjZWwoKTtcblx0XHR9KTtcblx0XHQkKCcjc2F2ZVNlYXJjaFNldHRpbmdzJykub24oJ2NsaWNrJywgZnVuY3Rpb24gKGUpIHtcblx0XHRcdE1vZHVsZUV4dGVuZGVkQ0RScy5zYXZlU2VhcmNoU2V0dGluZ3MoKTtcblx0XHR9KTtcblxuXHRcdE1vZHVsZUV4dGVuZGVkQ0RScy4kZ2xvYmFsU2VhcmNoLm9uKCdrZXl1cCcsIChlKSA9PiB7XG5cdFx0XHRpZiAoZS5rZXlDb2RlID09PSAxM1xuXHRcdFx0XHR8fCBlLmtleUNvZGUgPT09IDhcblx0XHRcdFx0fHwgTW9kdWxlRXh0ZW5kZWRDRFJzLiRnbG9iYWxTZWFyY2gudmFsKCkubGVuZ3RoID09PSAwKSB7XG5cdFx0XHRcdE1vZHVsZUV4dGVuZGVkQ0RScy5hcHBseUZpbHRlcigpO1xuXHRcdFx0fVxuXHRcdH0pO1xuXG5cdFx0TW9kdWxlRXh0ZW5kZWRDRFJzLiRmb3JtT2JqLmtleWRvd24oZnVuY3Rpb24oZXZlbnQpe1xuXHRcdFx0aWYoZXZlbnQua2V5Q29kZSA9PT0gMTMpIHtcblx0XHRcdFx0ZXZlbnQucHJldmVudERlZmF1bHQoKTtcblx0XHRcdFx0cmV0dXJuIGZhbHNlO1xuXHRcdFx0fVxuXHRcdH0pO1xuXG5cblx0XHRNb2R1bGVFeHRlbmRlZENEUnMuJGNkclRhYmxlLmRhdGFUYWJsZSh7XG5cdFx0XHRzZWFyY2g6IHtcblx0XHRcdFx0c2VhcmNoOiBNb2R1bGVFeHRlbmRlZENEUnMuZ2V0U2VhcmNoVGV4dCgpLFxuXHRcdFx0fSxcblx0XHRcdHNlcnZlclNpZGU6IHRydWUsXG5cdFx0XHRwcm9jZXNzaW5nOiB0cnVlLFxuXHRcdFx0Y29sdW1uRGVmczogW1xuXHRcdFx0XHR7IGRlZmF1bHRDb250ZW50OiBcIi1cIiwgIHRhcmdldHM6IFwiX2FsbFwifSxcblx0XHRcdF0sXG5cdFx0XHRhamF4OiB7XG5cdFx0XHRcdHVybDogYCR7Z2xvYmFsUm9vdFVybH0ke2lkVXJsfS9nZXRIaXN0b3J5YCxcblx0XHRcdFx0dHlwZTogJ1BPU1QnLFxuXHRcdFx0XHRkYXRhU3JjOiBmdW5jdGlvbihqc29uKSB7XG5cdFx0XHRcdFx0JCgnYS5pdGVtW2RhdGEtdGFiPVwiYWxsLWNhbGxzXCJdIGInKS5odG1sKCc6ICcranNvbi5yZWNvcmRzRmlsdGVyZWQpXG5cdFx0XHRcdFx0JCgnYS5pdGVtW2RhdGEtdGFiPVwiaW5jb21pbmctY2FsbHNcIl0gYicpLmh0bWwoJzogJytqc29uLnJlY29yZHNJbmNvbWluZylcblx0XHRcdFx0XHQkKCdhLml0ZW1bZGF0YS10YWI9XCJtaXNzZWQtY2FsbHNcIl0gYicpLmh0bWwoJzogJytqc29uLnJlY29yZHNNaXNzZWQpXG5cdFx0XHRcdFx0JCgnYS5pdGVtW2RhdGEtdGFiPVwib3V0Z29pbmctY2FsbHNcIl0gYicpLmh0bWwoJzogJytqc29uLnJlY29yZHNPdXRnb2luZylcblxuXHRcdFx0XHRcdGxldCB0eXBlQ2FsbCA9ICQoJyN0eXBlQ2FsbCBhLml0ZW0uYWN0aXZlJykuYXR0cignZGF0YS10YWInKTtcblx0XHRcdFx0XHRpZih0eXBlQ2FsbCA9PT0gJ2luY29taW5nLWNhbGxzJyl7XG5cdFx0XHRcdFx0XHRqc29uLnJlY29yZHNGaWx0ZXJlZCA9IGpzb24ucmVjb3Jkc0luY29taW5nO1xuXHRcdFx0XHRcdH1lbHNlIGlmKHR5cGVDYWxsID09PSAnbWlzc2VkLWNhbGxzJyl7XG5cdFx0XHRcdFx0XHRqc29uLnJlY29yZHNGaWx0ZXJlZCA9IGpzb24ucmVjb3Jkc01pc3NlZDtcblx0XHRcdFx0XHR9ZWxzZSBpZih0eXBlQ2FsbCA9PT0gJ291dGdvaW5nLWNhbGxzJyl7XG5cdFx0XHRcdFx0XHRqc29uLnJlY29yZHNGaWx0ZXJlZCA9IGpzb24ucmVjb3Jkc091dGdvaW5nO1xuXHRcdFx0XHRcdH1cblx0XHRcdFx0XHRyZXR1cm4ganNvbi5kYXRhO1xuXHRcdFx0XHR9XG5cdFx0XHR9LFxuXHRcdFx0cGFnaW5nOiB0cnVlLFxuXHRcdFx0c0RvbTogJ3J0aXAnLFxuXHRcdFx0ZGVmZXJSZW5kZXI6IHRydWUsXG5cdFx0XHRwYWdlTGVuZ3RoOiBNb2R1bGVFeHRlbmRlZENEUnMuY2FsY3VsYXRlUGFnZUxlbmd0aCgpLFxuXG5cdFx0XHQvKipcblx0XHRcdCAqIENvbnN0cnVjdHMgdGhlIENEUiByb3cuXG5cdFx0XHQgKiBAcGFyYW0ge0hUTUxFbGVtZW50fSByb3cgLSBUaGUgcm93IGVsZW1lbnQuXG5cdFx0XHQgKiBAcGFyYW0ge0FycmF5fSBkYXRhIC0gVGhlIHJvdyBkYXRhLlxuXHRcdFx0ICovXG5cdFx0XHRjcmVhdGVkUm93KHJvdywgZGF0YSkge1xuXHRcdFx0XHRsZXQgZGV0YWlsZWRJY29uID0gJyc7XG5cdFx0XHRcdGlmIChkYXRhLkRUX1Jvd0NsYXNzLmluZGV4T2YoXCJkZXRhaWxlZFwiKSA+PSAwKSB7XG5cdFx0XHRcdFx0ZGV0YWlsZWRJY29uID0gJzxpIGNsYXNzPVwiaWNvbiBjYXJldCBkb3duXCI+PC9pPic7XG5cdFx0XHRcdH1cblx0XHRcdFx0aWYoZGF0YS50eXBlQ2FsbCA9PT0gJzEnKXtcblx0XHRcdFx0XHQkKCd0ZCcsIHJvdykuZXEoMCkuaHRtbCgnPGkgY2xhc3M9XCJjdXN0b20tb3V0Z29pbmctaWNvbi0xNXgxNVwiPjwvaT4nK2RldGFpbGVkSWNvbik7XG5cdFx0XHRcdH1lbHNlIGlmKGRhdGEudHlwZUNhbGwgPT09ICcyJyl7XG5cdFx0XHRcdFx0JCgndGQnLCByb3cpLmVxKDApLmh0bWwoJzxpIGNsYXNzPVwiY3VzdG9tLWluY29taW5nLWljb24tMTV4MTVcIj48L2k+JytkZXRhaWxlZEljb24pO1xuXHRcdFx0XHR9ZWxzZSBpZihkYXRhLnR5cGVDYWxsID09PSAnMycpe1xuXHRcdFx0XHRcdCQoJ3RkJywgcm93KS5lcSgwKS5odG1sKCc8aSBjbGFzcz1cImN1c3RvbS1taXNzZWQtaWNvbi0xNXgxNVwiPjwvaT4nK2RldGFpbGVkSWNvbik7XG5cdFx0XHRcdH1lbHNle1xuXHRcdFx0XHRcdCQoJ3RkJywgcm93KS5lcSgwKS5odG1sKCcnK2RldGFpbGVkSWNvbik7XG5cdFx0XHRcdH1cblxuXHRcdFx0XHQkKCd0ZCcsIHJvdykuZXEoMSkuaHRtbChkYXRhWzBdKS5hZGRDbGFzcygncmlnaHQgYWxpZ25lZCcpOztcblx0XHRcdFx0JCgndGQnLCByb3cpLmVxKDIpXG5cdFx0XHRcdFx0Lmh0bWwoZGF0YVsxXSlcblx0XHRcdFx0XHQuYXR0cignZGF0YS1waG9uZScsZGF0YVsxXSlcblx0XHRcdFx0XHQuYWRkQ2xhc3MoJ25lZWQtdXBkYXRlJykuYWRkQ2xhc3MoJ3JpZ2h0IGFsaWduZWQnKTs7XG5cdFx0XHRcdCQoJ3RkJywgcm93KS5lcSgzKVxuXHRcdFx0XHRcdC5odG1sKGRhdGFbMl0pXG5cdFx0XHRcdFx0LmF0dHIoJ2RhdGEtcGhvbmUnLGRhdGFbMl0pXG5cdFx0XHRcdFx0LmFkZENsYXNzKCduZWVkLXVwZGF0ZScpO1xuXG5cdFx0XHRcdGxldCBkdXJhdGlvbiA9IGRhdGFbM107XG5cdFx0XHRcdGlmIChkYXRhLmlkcyAhPT0gJycpIHtcblx0XHRcdFx0XHRkdXJhdGlvbiArPSAnPGkgZGF0YS1pZHM9XCInICsgZGF0YS5pZHMgKyAnXCIgY2xhc3M9XCJmaWxlIGFsdGVybmF0ZSBvdXRsaW5lIGljb25cIj4nO1xuXHRcdFx0XHR9XG5cblx0XHRcdFx0bGV0IGxpbmVUZXh0ID0gZGF0YS5saW5lO1xuXHRcdFx0XHRpZihkYXRhLmRpZCAhPT0gXCJcIil7XG5cdFx0XHRcdFx0bGluZVRleHQgPSBgJHtkYXRhLmxpbmV9IDxhIGNsYXNzPVwidWkgbWluaSBiYXNpYyBsYWJlbFwiPiR7ZGF0YS5kaWR9PC9hPmA7XG5cdFx0XHRcdH1cblx0XHRcdFx0JCgndGQnLCByb3cpLmVxKDQpLmh0bWwobGluZVRleHQpLmFkZENsYXNzKCdyaWdodCBhbGlnbmVkJyk7XG5cblx0XHRcdFx0JCgndGQnLCByb3cpLmVxKDUpLmh0bWwoZGF0YS53YWl0VGltZSkuYWRkQ2xhc3MoJ3JpZ2h0IGFsaWduZWQnKTtcblx0XHRcdFx0JCgndGQnLCByb3cpLmVxKDYpLmh0bWwoZHVyYXRpb24pLmFkZENsYXNzKCdyaWdodCBhbGlnbmVkJyk7XG5cdFx0XHRcdCQoJ3RkJywgcm93KS5lcSg3KS5odG1sKGRhdGEuc3RhdGVDYWxsKS5hZGRDbGFzcygncmlnaHQgYWxpZ25lZCcpO1xuXHRcdFx0fSxcblxuXHRcdFx0LyoqXG5cdFx0XHQgKiBEcmF3IGV2ZW50IC0gZmlyZWQgb25jZSB0aGUgdGFibGUgaGFzIGNvbXBsZXRlZCBhIGRyYXcuXG5cdFx0XHQgKi9cblx0XHRcdGRyYXdDYWxsYmFjaygpIHtcblx0XHRcdFx0RXh0ZW5zaW9ucy51cGRhdGVQaG9uZXNSZXByZXNlbnQoJ25lZWQtdXBkYXRlJyk7XG5cdFx0XHR9LFxuXHRcdFx0bGFuZ3VhZ2U6IFNlbWFudGljTG9jYWxpemF0aW9uLmRhdGFUYWJsZUxvY2FsaXNhdGlvbixcblx0XHRcdG9yZGVyaW5nOiBmYWxzZSxcblx0XHR9KTtcblx0XHRNb2R1bGVFeHRlbmRlZENEUnMuZGF0YVRhYmxlID0gTW9kdWxlRXh0ZW5kZWRDRFJzLiRjZHJUYWJsZS5EYXRhVGFibGUoKTtcblx0XHRNb2R1bGVFeHRlbmRlZENEUnMuZGF0YVRhYmxlLm9uKCdkcmF3JywgKCkgPT4ge1xuXHRcdFx0TW9kdWxlRXh0ZW5kZWRDRFJzLiRnbG9iYWxTZWFyY2guY2xvc2VzdCgnZGl2JykucmVtb3ZlQ2xhc3MoJ2xvYWRpbmcnKTtcblx0XHR9KTtcblxuXHRcdE1vZHVsZUV4dGVuZGVkQ0RScy4kY2RyVGFibGUub24oJ2NsaWNrJywgJ3RyLm5lZ2F0aXZlJywgKGUpID0+IHtcblx0XHRcdGxldCBmaWx0ZXIgPSAkKGUudGFyZ2V0KS5hdHRyKCdkYXRhLXBob25lJyk7XG5cdFx0XHRpZiAoZmlsdGVyICE9PSB1bmRlZmluZWQgJiYgZmlsdGVyICE9PSAnJykge1xuXHRcdFx0XHRNb2R1bGVFeHRlbmRlZENEUnMuJGdsb2JhbFNlYXJjaC52YWwoZmlsdGVyKVxuXHRcdFx0XHRNb2R1bGVFeHRlbmRlZENEUnMuYXBwbHlGaWx0ZXIoKTtcblx0XHRcdFx0cmV0dXJuO1xuXHRcdFx0fVxuXHRcdFx0bGV0IGlkcyA9ICQoZS50YXJnZXQpLmF0dHIoJ2RhdGEtaWRzJyk7XG5cdFx0XHRpZiAoaWRzICE9PSB1bmRlZmluZWQgJiYgaWRzICE9PSAnJykge1xuXHRcdFx0XHR3aW5kb3cubG9jYXRpb24gPSBgJHtnbG9iYWxSb290VXJsfXN5c3RlbS1kaWFnbm9zdGljL2luZGV4Lz9maWxlbmFtZT1hc3Rlcmlzay92ZXJib3NlJmZpbHRlcj0ke2lkc31gO1xuXHRcdFx0fVxuXHRcdH0pO1xuXG5cdFx0Ly8gQWRkIGV2ZW50IGxpc3RlbmVyIGZvciBvcGVuaW5nIGFuZCBjbG9zaW5nIGRldGFpbHNcblx0XHRNb2R1bGVFeHRlbmRlZENEUnMuJGNkclRhYmxlLm9uKCdjbGljaycsICd0ci5kZXRhaWxlZCcsIChlKSA9PiB7XG5cdFx0XHRsZXQgaWRzID0gJChlLnRhcmdldCkuYXR0cignZGF0YS1pZHMnKTtcblx0XHRcdGlmIChpZHMgIT09IHVuZGVmaW5lZCAmJiBpZHMgIT09ICcnKSB7XG5cdFx0XHRcdHdpbmRvdy5sb2NhdGlvbiA9IGAke2dsb2JhbFJvb3RVcmx9c3lzdGVtLWRpYWdub3N0aWMvaW5kZXgvP2ZpbGVuYW1lPWFzdGVyaXNrL3ZlcmJvc2UmZmlsdGVyPSR7aWRzfWA7XG5cdFx0XHRcdHJldHVybjtcblx0XHRcdH1cblx0XHRcdGxldCBmaWx0ZXIgPSAkKGUudGFyZ2V0KS5hdHRyKCdkYXRhLXBob25lJyk7XG5cdFx0XHRpZiAoZmlsdGVyICE9PSB1bmRlZmluZWQgJiYgZmlsdGVyICE9PSAnJykge1xuXHRcdFx0XHRNb2R1bGVFeHRlbmRlZENEUnMuJGdsb2JhbFNlYXJjaC52YWwoZmlsdGVyKVxuXHRcdFx0XHRNb2R1bGVFeHRlbmRlZENEUnMuYXBwbHlGaWx0ZXIoKTtcblx0XHRcdFx0cmV0dXJuO1xuXHRcdFx0fVxuXG5cdFx0XHRjb25zdCB0ciA9ICQoZS50YXJnZXQpLmNsb3Nlc3QoJ3RyJyk7XG5cdFx0XHRjb25zdCByb3cgPSBNb2R1bGVFeHRlbmRlZENEUnMuZGF0YVRhYmxlLnJvdyh0cik7XG5cdFx0XHRpZihyb3cubGVuZ3RoID09PSAwKXtcblx0XHRcdFx0cmV0dXJuO1xuXHRcdFx0fVxuXHRcdFx0aWYgKHRyLmhhc0NsYXNzKCdzaG93bicpKSB7XG5cdFx0XHRcdC8vIFRoaXMgcm93IGlzIGFscmVhZHkgb3BlbiAtIGNsb3NlIGl0XG5cdFx0XHRcdCQoJ3RyW2RhdGEtcm93LWlkPVwiJyt0ci5hdHRyKCdpZCcpKyctZGV0YWlsZWRcIicpLnJlbW92ZSgpO1xuXHRcdFx0XHR0ci5yZW1vdmVDbGFzcygnc2hvd24nKTtcblx0XHRcdH0gZWxzZSB7XG5cdFx0XHRcdC8vIE9wZW4gdGhpcyByb3dcblx0XHRcdFx0dHIuYWZ0ZXIoTW9kdWxlRXh0ZW5kZWRDRFJzLnNob3dSZWNvcmRzKHJvdy5kYXRhKCkgLCB0ci5hdHRyKCdpZCcpKSk7XG5cdFx0XHRcdHRyLmFkZENsYXNzKCdzaG93bicpO1xuXHRcdFx0XHQkKCd0cltkYXRhLXJvdy1pZD1cIicrdHIuYXR0cignaWQnKSsnLWRldGFpbGVkXCInKS5lYWNoKChpbmRleCwgcGxheWVyUm93KSA9PiB7XG5cdFx0XHRcdFx0Y29uc3QgaWQgPSAkKHBsYXllclJvdykuYXR0cignaWQnKTtcblx0XHRcdFx0XHRyZXR1cm4gbmV3IENEUlBsYXllcihpZCk7XG5cdFx0XHRcdH0pO1xuXHRcdFx0XHRFeHRlbnNpb25zLnVwZGF0ZVBob25lc1JlcHJlc2VudCgnbmVlZC11cGRhdGUnKTtcblx0XHRcdH1cblx0XHR9KTtcblxuXHRcdHdpbmRvd1tjbGFzc05hbWVdLnVwZGF0ZVN5bmNTdGF0ZSgpO1xuXHRcdHNldEludGVydmFsKHdpbmRvd1tjbGFzc05hbWVdLnVwZGF0ZVN5bmNTdGF0ZSwgNTAwMCk7XG5cdH0sXG5cblx0LyoqXG5cdCAqXG5cdCAqL1xuXHR1cGRhdGVTeW5jU3RhdGUoKXtcblx0XHQvLyDQktGL0L/QvtC70L3Rj9C10LwgR0VULdC30LDQv9GA0L7RgVxuXHRcdGxldCBkaXZQcm9ncmVzcyA9ICQoXCIjc3luYy1wcm9ncmVzc1wiKTtcblx0XHQkLmFqYXgoe1xuXHRcdFx0dXJsOiBgJHtnbG9iYWxSb290VXJsfSR7aWRVcmx9L2dldFN0YXRlYCxcblx0XHRcdG1ldGhvZDogJ0dFVCcsXG5cdFx0XHRzdWNjZXNzOiBmdW5jdGlvbihyZXNwb25zZSkge1xuXHRcdFx0XHRpZihyZXNwb25zZS5zdGF0ZURhdGEubGFzdElkIC0gcmVzcG9uc2Uuc3RhdGVEYXRhLm5vd0lkID4gMCl7XG5cdFx0XHRcdFx0ZGl2UHJvZ3Jlc3Muc2hvdygpO1xuXHRcdFx0XHR9ZWxzZXtcblx0XHRcdFx0XHRkaXZQcm9ncmVzcy5oaWRlKCk7XG5cdFx0XHRcdH1cblx0XHRcdFx0ZGl2UHJvZ3Jlc3MucHJvZ3Jlc3Moe1xuXHRcdFx0XHRcdHRvdGFsOiByZXNwb25zZS5zdGF0ZURhdGEubGFzdElkLCB2YWx1ZTogcmVzcG9uc2Uuc3RhdGVEYXRhLm5vd0lkLFxuXHRcdFx0XHRcdHRleHQ6IHtcblx0XHRcdFx0XHRcdGFjdGl2ZSAgOiBnbG9iYWxUcmFuc2xhdGUucmVwTW9kdWxlRXh0ZW5kZWRDRFJzX3N5bmNTdGF0ZVxuXHRcdFx0XHRcdH0sXG5cdFx0XHRcdFx0ZXJyb3I6IGZ1bmN0aW9uKGpxWEhSLCB0ZXh0U3RhdHVzLCBlcnJvclRocm93bikge1xuXHRcdFx0XHRcdFx0Ly8g0J7QsdGA0LDQsdC+0YLQutCwINC+0YjQuNCx0LrQuFxuXHRcdFx0XHRcdFx0Y29uc29sZS5lcnJvcign0J7RiNC40LHQutCwINC30LDQv9GA0L7RgdCwOicsIHRleHRTdGF0dXMsIGVycm9yVGhyb3duKTtcblx0XHRcdFx0XHR9XG5cdFx0XHRcdH0pXG5cdFx0XHR9XG5cdFx0fSk7XG5cdH0sXG5cblx0LyoqXG5cdCAqIFNob3dzIGEgc2V0IG9mIGNhbGwgcmVjb3JkcyB3aGVuIGEgcm93IGlzIGNsaWNrZWQuXG5cdCAqIEBwYXJhbSB7QXJyYXl9IGRhdGEgLSBUaGUgcm93IGRhdGEuXG5cdCAqIEByZXR1cm5zIHtzdHJpbmd9IFRoZSBIVE1MIHJlcHJlc2VudGF0aW9uIG9mIHRoZSBjYWxsIHJlY29yZHMuXG5cdCAqL1xuXHRzaG93UmVjb3JkcyhkYXRhLCBpZCkge1xuXHRcdGxldCBodG1sUGxheWVyID0gJyc7XG5cdFx0ZGF0YVs0XS5mb3JFYWNoKChyZWNvcmQsIGkpID0+IHtcblx0XHRcdGxldCBzcmNBdWRpbyA9ICcnO1xuXHRcdFx0bGV0IHNyY0Rvd25sb2FkQXVkaW8gPSAnJztcblx0XHRcdGlmICghKHJlY29yZC5yZWNvcmRpbmdmaWxlID09PSB1bmRlZmluZWQgfHwgcmVjb3JkLnJlY29yZGluZ2ZpbGUgPT09IG51bGwgfHwgcmVjb3JkLnJlY29yZGluZ2ZpbGUubGVuZ3RoID09PSAwKSkge1xuXHRcdFx0XHRsZXQgcmVjb3JkRmlsZU5hbWUgPSBgcmVjb3JkXyR7cmVjb3JkLnNyY19udW19X3RvXyR7cmVjb3JkLmRzdF9udW19X2Zyb21fJHtkYXRhWzBdfWA7XG5cdFx0XHRcdHJlY29yZEZpbGVOYW1lLnJlcGxhY2UoL1teXFx3XFxzIT9dL2csICcnKTtcblx0XHRcdFx0cmVjb3JkRmlsZU5hbWUgPSBlbmNvZGVVUklDb21wb25lbnQocmVjb3JkRmlsZU5hbWUpO1xuXHRcdFx0XHRjb25zdCByZWNvcmRGaWxlVXJpID0gZW5jb2RlVVJJQ29tcG9uZW50KHJlY29yZC5yZWNvcmRpbmdmaWxlKTtcblx0XHRcdFx0c3JjQXVkaW8gPSBgL3BieGNvcmUvYXBpL2Nkci92Mi9wbGF5YmFjaz92aWV3PSR7cmVjb3JkRmlsZVVyaX1gO1xuXHRcdFx0XHRzcmNEb3dubG9hZEF1ZGlvID0gYC9wYnhjb3JlL2FwaS9jZHIvdjIvcGxheWJhY2s/dmlldz0ke3JlY29yZEZpbGVVcml9JmRvd25sb2FkPTEmZmlsZW5hbWU9JHtyZWNvcmRGaWxlTmFtZX0ubXAzYDtcblx0XHRcdH1cblxuXHRcdFx0aHRtbFBsYXllciArPWBcblx0XHRcdDx0ciBpZD1cIiR7cmVjb3JkLmlkfVwiIGRhdGEtcm93LWlkPVwiJHtpZH0tZGV0YWlsZWRcIiBjbGFzcz1cIndhcm5pbmcgZGV0YWlsZWQgb2RkIHNob3duXCIgcm9sZT1cInJvd1wiPlxuXHRcdFx0XHQ8dGQ+PC90ZD5cblx0XHRcdFx0PHRkIGNsYXNzPVwicmlnaHQgYWxpZ25lZFwiPiR7cmVjb3JkLnN0YXJ0fTwvdGQ+XG5cdFx0XHRcdDx0ZCBkYXRhLXBob25lPVwiJHtyZWNvcmQuc3JjX251bX1cIiBjbGFzcz1cInJpZ2h0IGFsaWduZWQgbmVlZC11cGRhdGVcIj4ke3JlY29yZC5zcmNfbnVtfTwvdGQ+XG5cdFx0XHQgICBcdDx0ZCBkYXRhLXBob25lPVwiJHtyZWNvcmQuZHN0X251bX1cIiBjbGFzcz1cImxlZnQgYWxpZ25lZCBuZWVkLXVwZGF0ZVwiPiR7cmVjb3JkLmRzdF9udW19PC90ZD5cblx0XHRcdFx0PHRkIGNsYXNzPVwicmlnaHQgYWxpZ25lZFwiPlx0XHRcdFxuXHRcdFx0XHQ8L3RkPlxuXHRcdFx0XHQ8dGQgY2xhc3M9XCJyaWdodCBhbGlnbmVkXCI+JHtyZWNvcmQud2FpdFRpbWV9PC90ZD5cblx0XHRcdFx0PHRkIGNsYXNzPVwicmlnaHQgYWxpZ25lZFwiPlxuXHRcdFx0XHRcdDxpIGNsYXNzPVwidWkgaWNvbiBwbGF5XCI+PC9pPlxuXHRcdFx0XHRcdDxhdWRpbyBwcmVsb2FkPVwibWV0YWRhdGFcIiBpZD1cImF1ZGlvLXBsYXllci0ke3JlY29yZC5pZH1cIiBzcmM9XCIke3NyY0F1ZGlvfVwiPjwvYXVkaW8+XG5cdFx0XHRcdFx0JHtyZWNvcmQuYmlsbHNlY31cblx0XHRcdFx0XHQ8aSBjbGFzcz1cInVpIGljb24gZG93bmxvYWRcIiBkYXRhLXZhbHVlPVwiJHtzcmNEb3dubG9hZEF1ZGlvfVwiPjwvaT5cblx0XHRcdFx0PC90ZD5cblx0XHRcdFx0PHRkIGNsYXNzPVwicmlnaHQgYWxpZ25lZFwiIGRhdGEtc3RhdGUtaW5kZXg9XCIke3JlY29yZC5zdGF0ZUNhbGxJbmRleH1cIj4ke3JlY29yZC5zdGF0ZUNhbGx9PC90ZD5cblx0XHRcdDwvdHI+YFxuXHRcdH0pO1xuXHRcdHJldHVybiBodG1sUGxheWVyO1xuXHR9LFxuXG5cdGNhbGN1bGF0ZVBhZ2VMZW5ndGgoKSB7XG5cdFx0Ly8gQ2FsY3VsYXRlIHJvdyBoZWlnaHRcblx0XHRsZXQgcm93SGVpZ2h0ID0gTW9kdWxlRXh0ZW5kZWRDRFJzLiRjZHJUYWJsZS5maW5kKCd0Ym9keSA+IHRyJykuZmlyc3QoKS5vdXRlckhlaWdodCgpO1xuXG5cdFx0Ly8gQ2FsY3VsYXRlIHdpbmRvdyBoZWlnaHQgYW5kIGF2YWlsYWJsZSBzcGFjZSBmb3IgdGFibGVcblx0XHRjb25zdCB3aW5kb3dIZWlnaHQgPSB3aW5kb3cuaW5uZXJIZWlnaHQ7XG5cdFx0Y29uc3QgaGVhZGVyRm9vdGVySGVpZ2h0ID0gNDAwICsgNTA7IC8vIEVzdGltYXRlIGhlaWdodCBmb3IgaGVhZGVyLCBmb290ZXIsIGFuZCBvdGhlciBlbGVtZW50c1xuXG5cdFx0Ly8gQ2FsY3VsYXRlIG5ldyBwYWdlIGxlbmd0aFxuXHRcdHJldHVybiBNYXRoLm1heChNYXRoLmZsb29yKCh3aW5kb3dIZWlnaHQgLSBoZWFkZXJGb290ZXJIZWlnaHQpIC8gcm93SGVpZ2h0KSwgNSk7XG5cdH0sXG5cblxuXHRhZGRpdGlvbmFsRXhwb3J0RnVuY3Rpb25zKCl7XG5cdFx0bGV0IGJvZHkgPSAkKCdib2R5Jyk7XG5cdFx0Ym9keS5vbignY2xpY2snLCAnI2FkZC1uZXctYnV0dG9uJywgZnVuY3Rpb24gKGUpIHtcblx0XHRcdGxldCBpZCA9ICdub25lLScrRGF0ZS5ub3coKTtcblx0XHRcdGxldCBuZXdSb3cgXHQ9ICQoJzx0cj4nKS5hdHRyKCdpZCcsIGlkKTtcblx0XHRcdGxldCBuYW1lQ2VsbCA9ICQoJzx0ZD4nKS5hdHRyKCdkYXRhLWxhYmVsJywgJ25hbWUnKS5hZGRDbGFzcygncmlnaHQgYWxpZ25lZCcpLmh0bWwoJzxkaXYgY2xhc3M9XCJ1aSBtaW5pIGljb24gaW5wdXRcIj48aW5wdXQgdHlwZT1cInRleHRcIiBwbGFjZWhvbGRlcj1cIlwiIHZhbHVlPVwiXCI+PC9kaXY+Jyk7XG5cdFx0XHRsZXQgdXNlcnNDZWxsIFx0PSAkKCc8dGQ+JykuYXR0cignZGF0YS1sYWJlbCcsICd1c2VycycpLmh0bWwoJzxkaXYgY2xhc3M9XCJ1aSBtdWx0aXBsZSBkcm9wZG93blwiPicgKyAkKCcjdXNlcnMtc2VsZWN0b3InKS5odG1sKCkgKyAgJzxkaXY+Jyk7XG5cdFx0XHRsZXQgYnV0dG9uc0NlbGxcdD0gJCgnPHRkPicpLmF0dHIoJ2RhdGEtbGFiZWwnLCAnYnV0dG9ucycpLmh0bWwoJzxkaXYgY2xhc3M9XCJ1aSBidXR0b25zXCI+XFxuJyArXG5cdFx0XHRcdCcgICAgICAgICAgICAgICAgICA8YnV0dG9uIGNsYXNzPVwiY29tcGFjdCB1aSBpY29uIGJhc2ljIGJ1dHRvblwiIGRhdGEtYWN0aW9uPVwic2V0dGluZ3NcIiBvbmNsaWNrPVwiTW9kdWxlRXh0ZW5kZWRDRFJzLnNob3dSdWxlT3B0aW9ucyhcXCcnK2lkKydcXCcpXCI+XFxuJyArXG5cdFx0XHRcdCcgICAgICAgICAgICAgICAgICAgIDxpIGNsYXNzPVwiY29nIGljb25cIj48L2k+XFxuJyArXG5cdFx0XHRcdCcgICAgICAgICAgICAgICAgICA8L2J1dHRvbj5cXG4nICtcblx0XHRcdFx0JyAgICAgICAgICAgICAgICAgIDxidXR0b24gY2xhc3M9XCJjb21wYWN0IHVpIGljb24gYmFzaWMgYnV0dG9uXCIgZGF0YS1hY3Rpb249XCJyZW1vdmVcIiBvbmNsaWNrPVwiTW9kdWxlRXh0ZW5kZWRDRFJzLnJlbW92ZVJ1bGUoXFwnJytpZCsnXFwnKVwiPlxcbicgK1xuXHRcdFx0XHQnICAgICAgICAgICAgICAgICAgICA8aSBjbGFzcz1cImljb24gdHJhc2ggcmVkXCI+PC9pPlxcbicgK1xuXHRcdFx0XHQnICAgICAgICAgICAgICAgICAgPC9idXR0b24+XFxuJyArXG5cdFx0XHRcdCcgICAgICAgICAgICAgICAgPC9kaXY+Jyk7XG5cdFx0XHRsZXQgaGVhZGVyc0NlbGwgPSAkKCc8dGQ+JykuYXR0cignZGF0YS1sYWJlbCcsICdoZWFkZXJzJykuYWRkQ2xhc3MoJ3JpZ2h0IGFsaWduZWQnKS5hdHRyKCdzdHlsZScsICdkaXNwbGF5OiBub25lJykuaHRtbCgnPGRpdiBjbGFzcz1cInVpIG1vZGFsIHNlZ21lbnRcIiBkYXRhLWlkPVwiJytpZCsnXCI+XFxuJyArXG5cdFx0XHRcdCcgICAgICAgICAgICAgICAgICA8aSBjbGFzcz1cImNsb3NlIGljb25cIj48L2k+XFxuJyArXG5cdFx0XHRcdCcgICAgICAgICAgICAgICAgICAgIDxkaXYgY2xhc3M9XCJ1aSBmb3JtXCI+XFxuJyArXG5cdFx0XHRcdCcgICAgICAgICAgICAgICAgICAgICAgPGRpdiBjbGFzcz1cImZpZWxkXCI+XFxuJyArXG5cdFx0XHRcdCcgICAgICAgICAgICAgICAgICAgICAgICA8bGFiZWw+SFRUUCBIZWFkZXJzPC9sYWJlbD5cXG4nICtcblx0XHRcdFx0JyAgICAgICAgICAgICAgICAgICAgICAgIDx0ZXh0YXJlYT48L3RleHRhcmVhPlxcbicgK1xuXHRcdFx0XHQnICAgICAgICAgICAgICAgICAgICAgIDwvZGl2PlxcbicgK1xuXHRcdFx0XHQnICAgICAgICAgICAgICAgICAgICAgIDxkaXYgY2xhc3M9XCJmaWVsZFwiPlxcbicgK1xuXHRcdFx0XHQnICAgICAgICAgICAgICAgICAgICAgICAgPGxhYmVsPlVSTDwvbGFiZWw+XFxuJyArXG5cdFx0XHRcdCcgICAgICAgICAgICAgICAgICAgICAgICA8ZGl2IGNsYXNzPVwidWkgaWNvbiBpbnB1dFwiPjxpbnB1dCBkYXRhLWxhYmVsPVwiZHN0VXJsXCIgdHlwZT1cInRleHRcIiBwbGFjZWhvbGRlcj1cIlwiIHZhbHVlPVwiXCI+PC9kaXY+XFxuJyArXG5cdFx0XHRcdCcgICAgICAgICAgICAgICAgICAgICAgPC9kaXY+XFxuJyArXG5cdFx0XHRcdCcgICAgICAgICAgICAgICAgICAgIDwvZGl2PlxcbicgK1xuXHRcdFx0XHQnICAgICAgICAgICAgICAgICAgPGRpdiBjbGFzcz1cImFjdGlvbnNcIj5cXG4nICtcblx0XHRcdFx0JyAgICAgICAgICAgICAgICAgICAgPGRpdiBjbGFzcz1cInVpIHBvc2l0aXZlIHJpZ2h0IGxhYmVsZWQgaWNvbiBidXR0b25cIj5cXG4nICtcblx0XHRcdFx0JyAgICAgICAgICAgICAgICAgICAgICDQl9Cw0LLQtdGA0YjQuNGC0Ywg0YDQtdC00LDQutGC0LjRgNC+0LLQsNC90LjQtVxcbicgK1xuXHRcdFx0XHQnICAgICAgICAgICAgICAgICAgICAgIDxpIGNsYXNzPVwiY2hlY2ttYXJrIGljb25cIj48L2k+XFxuJyArXG5cdFx0XHRcdCcgICAgICAgICAgICAgICAgICAgIDwvZGl2PlxcbicgK1xuXHRcdFx0XHQnICAgICAgICAgICAgICAgICAgPC9kaXY+XFxuJyArXG5cdFx0XHRcdCcgICAgICAgICAgICAgICAgPC9kaXY+Jyk7XG5cdFx0XHRuZXdSb3cuYXBwZW5kKG5hbWVDZWxsLCB1c2Vyc0NlbGwsIGhlYWRlcnNDZWxsLCBidXR0b25zQ2VsbCk7XG5cdFx0XHQkKCcjc3luYy1ydWxlcycpLmFwcGVuZChuZXdSb3cpO1xuXHRcdFx0JCgnZGl2LmRyb3Bkb3duJykuZHJvcGRvd24oKTtcblx0XHR9KTtcblxuXHRcdGJvZHkub24oJ2NsaWNrJywgJyNzYXZlLWJ1dHRvbicsIGZ1bmN0aW9uIChlKSB7XG5cdFx0XHQvLyDQntCx0L7QudC00LjRgtC1INGN0LvQtdC80LXQvdGC0Ysg0LIg0YbQuNC60LvQtVxuXHRcdFx0JChcImlucHV0XCIpLmVhY2goZnVuY3Rpb24oaW5kZXgpIHtcblx0XHRcdFx0JCh0aGlzKS5hdHRyKCd2YWx1ZScsICQodGhpcykudmFsKCkpO1xuXHRcdFx0fSk7XG5cblx0XHRcdGxldCBkYXRhID0gW107XG5cdFx0XHRsZXQgdGFibGUgPSAkKCcjc3luYy1ydWxlcycpO1xuXHRcdFx0bGV0IHJvd3MgPSB0YWJsZS5maW5kKCd0Ym9keSB0cicpO1xuXHRcdFx0cm93cy5lYWNoKGZ1bmN0aW9uKCkge1xuXHRcdFx0XHRsZXQgcm93ID0gJCh0aGlzKTtcblx0XHRcdFx0bGV0IHJvd0RhdGEgPSB7XG5cdFx0XHRcdFx0aWQ6IHJvdy5hdHRyKCdpZCcpLFxuXHRcdFx0XHRcdG5hbWU6ICByb3cuZmluZCgndGRbZGF0YS1sYWJlbD1cIm5hbWVcIl0gaW5wdXQnKS52YWwoKSxcblx0XHRcdFx0XHRkc3RVcmw6ICAkKCcudWkubW9kYWxbZGF0YS1pZD1cIicrcm93LmF0dHIoJ2lkJykrJ1wiXSBpbnB1dFtkYXRhLWxhYmVsPVwiZHN0VXJsXCJdJykudmFsKCksXG5cdFx0XHRcdFx0dXNlcnM6ICAgcm93LmZpbmQoJ3RkW2RhdGEtbGFiZWw9XCJ1c2Vyc1wiXSBkaXYuZHJvcGRvd24nKS5kcm9wZG93bignZ2V0IHZhbHVlJyksXG5cdFx0XHRcdFx0aGVhZGVyczogJCgnLnVpLm1vZGFsW2RhdGEtaWQ9XCInK3Jvdy5hdHRyKCdpZCcpKydcIl0gdGV4dGFyZWEnKS52YWwoKVxuXHRcdFx0XHR9O1xuXHRcdFx0XHRkYXRhLnB1c2gocm93RGF0YSk7XG5cdFx0XHR9KTtcblx0XHRcdCQuYWpheCh7XG5cdFx0XHRcdHR5cGU6IFwiUE9TVFwiLFxuXHRcdFx0XHR1cmw6ICBnbG9iYWxSb290VXJsICsgaWRVcmwgKyBcIi9zYXZlXCIsXG5cdFx0XHRcdGRhdGE6IHtydWxlczogZGF0YX0sXG5cdFx0XHRcdHN1Y2Nlc3M6IGZ1bmN0aW9uKHJlc3BvbnNlKSB7XG5cdFx0XHRcdFx0Y29uc29sZS5sb2coXCLQo9GB0L/QtdGI0L3Ri9C5INC30LDQv9GA0L7RgVwiLCByZXNwb25zZSk7XG5cdFx0XHRcdFx0Zm9yIChsZXQga2V5IGluIHJlc3BvbnNlLnJ1bGVTYXZlUmVzdWx0KSB7XG5cdFx0XHRcdFx0XHRpZiAocmVzcG9uc2UucnVsZVNhdmVSZXN1bHQuaGFzT3duUHJvcGVydHkoa2V5KSkge1xuXHRcdFx0XHRcdFx0XHRsZXQgc3luY1J1bGVzID0gJCgnI3N5bmMtcnVsZXMgdHIjJytrZXkpO1xuXHRcdFx0XHRcdFx0XHRsZXQgaHRtbCA9IHN5bmNSdWxlcy5odG1sKCk7XG5cdFx0XHRcdFx0XHRcdHN5bmNSdWxlcy5odG1sKGh0bWwucmVwbGFjZSgobmV3IFJlZ0V4cChrZXksIFwiZ1wiKSksIHJlc3BvbnNlLnJ1bGVTYXZlUmVzdWx0W2tleV0pKTtcblx0XHRcdFx0XHRcdFx0c3luY1J1bGVzLmF0dHIoJ2lkJywgcmVzcG9uc2UucnVsZVNhdmVSZXN1bHRba2V5XSk7XG5cdFx0XHRcdFx0XHRcdCQoJy51aS5tb2RhbFtkYXRhLWlkPVwiJytrZXkrJ1wiXScpLmF0dHIoJ2RhdGEtaWQnLCByZXNwb25zZS5ydWxlU2F2ZVJlc3VsdFtrZXldKTtcblx0XHRcdFx0XHRcdH1cblx0XHRcdFx0XHR9XG5cdFx0XHRcdH0sXG5cdFx0XHRcdGVycm9yOiBmdW5jdGlvbih4aHIsIHN0YXR1cywgZXJyb3IpIHtcblx0XHRcdFx0XHRjb25zb2xlLmVycm9yKFwi0J7RiNC40LHQutCwINC30LDQv9GA0L7RgdCwXCIsIHN0YXR1cywgZXJyb3IpO1xuXHRcdFx0XHR9XG5cdFx0XHR9KTtcblx0XHR9KTtcblx0fSxcblx0c2hvd1J1bGVPcHRpb25zKGlkKSB7XG5cdFx0JCgnLnVpLm1vZGFsW2RhdGEtaWQ9XCInK2lkKydcIl0nKS5tb2RhbCh7Y2xvc2FibGUgIDogdHJ1ZSwgfSkubW9kYWwoJ3Nob3cnKTtcblx0fSxcblx0cmVtb3ZlUnVsZShpZCkge1xuXHRcdCQuYWpheCh7XG5cdFx0XHR0eXBlOiBcIlBPU1RcIixcblx0XHRcdHVybDogIGdsb2JhbFJvb3RVcmwgKyBpZFVybCArIFwiL2RlbGV0ZVwiLFxuXHRcdFx0ZGF0YToge1xuXHRcdFx0XHR0YWJsZTogJ0V4cG9ydFJ1bGVzJyxcblx0XHRcdFx0aWQ6IGlkXG5cdFx0XHR9LFxuXHRcdFx0c3VjY2VzczogZnVuY3Rpb24ocmVzcG9uc2UpIHtcblx0XHRcdFx0Y29uc29sZS5sb2coXCLQo9GB0L/QtdGI0L3Ri9C5INC30LDQv9GA0L7RgVwiLCByZXNwb25zZSk7XG5cdFx0XHRcdCQoJyNzeW5jLXJ1bGVzIHRyIycraWQpLnJlbW92ZSgpO1xuXHRcdFx0fSxcblx0XHRcdGVycm9yOiBmdW5jdGlvbih4aHIsIHN0YXR1cywgZXJyb3IpIHtcblx0XHRcdFx0Y29uc29sZS5lcnJvcihcItCe0YjQuNCx0LrQsCDQt9Cw0L/RgNC+0YHQsFwiLCBzdGF0dXMsIGVycm9yKTtcblx0XHRcdH1cblx0XHR9KTtcblx0fSxcblxuXHQvKipcblx0ICogSW5pdGlhbGl6ZXMgdGhlIGRhdGUgcmFuZ2Ugc2VsZWN0b3IuXG5cdCAqL1xuXHRpbml0aWFsaXplRGF0ZVJhbmdlU2VsZWN0b3IoKSB7XG5cblx0XHRjb25zdCBwZXJpb2QgPSBNb2R1bGVFeHRlbmRlZENEUnMuJGRhdGVSYW5nZVNlbGVjdG9yLmF0dHIoJ2RhdGEtZGVmLXZhbHVlJyk7XG5cdFx0bGV0IGRlZlBlcmlvZCA9IFttb21lbnQoKSxtb21lbnQoKV07XG5cdFx0aWYocGVyaW9kICE9PSAnJyAmJiBwZXJpb2QgIT09IHVuZGVmaW5lZCl7XG5cdFx0XHRsZXQgcGVyaW9kcyA9IE1vZHVsZUV4dGVuZGVkQ0RScy5nZXRTdGFuZGFyZFBlcmlvZHMoKTtcblx0XHRcdGlmKHBlcmlvZHNbcGVyaW9kXSAhPT0gdW5kZWZpbmVkKXtcblx0XHRcdFx0ZGVmUGVyaW9kID0gcGVyaW9kc1twZXJpb2RdO1xuXHRcdFx0fVxuXHRcdH1cblxuXHRcdGNvbnN0IG9wdGlvbnMgPSB7fTtcblx0XHRvcHRpb25zLnJhbmdlcyA9IHtcblx0XHRcdFtnbG9iYWxUcmFuc2xhdGUucmVwTW9kdWxlRXh0ZW5kZWRDRFJzX2Nkcl9jYWxfVG9kYXldOiBbbW9tZW50KCksIG1vbWVudCgpXSxcblx0XHRcdFtnbG9iYWxUcmFuc2xhdGUucmVwTW9kdWxlRXh0ZW5kZWRDRFJzX2Nkcl9jYWxfWWVzdGVyZGF5XTogW21vbWVudCgpLnN1YnRyYWN0KDEsICdkYXlzJyksIG1vbWVudCgpLnN1YnRyYWN0KDEsICdkYXlzJyldLFxuXHRcdFx0W2dsb2JhbFRyYW5zbGF0ZS5yZXBNb2R1bGVFeHRlbmRlZENEUnNfY2RyX2NhbF9MYXN0V2Vla106IFttb21lbnQoKS5zdWJ0cmFjdCg2LCAnZGF5cycpLCBtb21lbnQoKV0sXG5cdFx0XHRbZ2xvYmFsVHJhbnNsYXRlLnJlcE1vZHVsZUV4dGVuZGVkQ0RSc19jZHJfY2FsX0xhc3QzMERheXNdOiBbbW9tZW50KCkuc3VidHJhY3QoMjksICdkYXlzJyksIG1vbWVudCgpXSxcblx0XHRcdFtnbG9iYWxUcmFuc2xhdGUucmVwTW9kdWxlRXh0ZW5kZWRDRFJzX2Nkcl9jYWxfVGhpc01vbnRoXTogW21vbWVudCgpLnN0YXJ0T2YoJ21vbnRoJyksIG1vbWVudCgpLmVuZE9mKCdtb250aCcpXSxcblx0XHRcdFtnbG9iYWxUcmFuc2xhdGUucmVwTW9kdWxlRXh0ZW5kZWRDRFJzX2Nkcl9jYWxfTGFzdE1vbnRoXTogW21vbWVudCgpLnN1YnRyYWN0KDEsICdtb250aCcpLnN0YXJ0T2YoJ21vbnRoJyksIG1vbWVudCgpLnN1YnRyYWN0KDEsICdtb250aCcpLmVuZE9mKCdtb250aCcpXSxcblx0XHR9O1xuXHRcdG9wdGlvbnMuYWx3YXlzU2hvd0NhbGVuZGFycyA9IHRydWU7XG5cdFx0b3B0aW9ucy5hdXRvVXBkYXRlSW5wdXQgPSB0cnVlO1xuXHRcdG9wdGlvbnMubGlua2VkQ2FsZW5kYXJzID0gdHJ1ZTtcblx0XHRvcHRpb25zLm1heERhdGUgPSBtb21lbnQoKS5lbmRPZignbW9udGgnKTtcblx0XHRvcHRpb25zLmxvY2FsZSA9IHtcblx0XHRcdGZvcm1hdDogJ0REL01NL1lZWVknLFxuXHRcdFx0c2VwYXJhdG9yOiAnIC0gJyxcblx0XHRcdGFwcGx5TGFiZWw6IGdsb2JhbFRyYW5zbGF0ZS5jYWxfQXBwbHlCdG4sXG5cdFx0XHRjYW5jZWxMYWJlbDogZ2xvYmFsVHJhbnNsYXRlLmNhbF9DYW5jZWxCdG4sXG5cdFx0XHRmcm9tTGFiZWw6IGdsb2JhbFRyYW5zbGF0ZS5jYWxfZnJvbSxcblx0XHRcdHRvTGFiZWw6IGdsb2JhbFRyYW5zbGF0ZS5jYWxfdG8sXG5cdFx0XHRjdXN0b21SYW5nZUxhYmVsOiBnbG9iYWxUcmFuc2xhdGUuY2FsX0N1c3RvbVBlcmlvZCxcblx0XHRcdGRheXNPZldlZWs6IFNlbWFudGljTG9jYWxpemF0aW9uLmNhbGVuZGFyVGV4dC5kYXlzLFxuXHRcdFx0bW9udGhOYW1lczogU2VtYW50aWNMb2NhbGl6YXRpb24uY2FsZW5kYXJUZXh0Lm1vbnRocyxcblx0XHRcdGZpcnN0RGF5OiAxLFxuXHRcdH07XG5cdFx0b3B0aW9ucy5zdGFydERhdGUgPSBkZWZQZXJpb2RbMF07XG5cdFx0b3B0aW9ucy5lbmREYXRlICAgPSBkZWZQZXJpb2RbMV07XG5cdFx0TW9kdWxlRXh0ZW5kZWRDRFJzLiRkYXRlUmFuZ2VTZWxlY3Rvci5kYXRlcmFuZ2VwaWNrZXIoXG5cdFx0XHRvcHRpb25zLFxuXHRcdFx0TW9kdWxlRXh0ZW5kZWRDRFJzLmNiRGF0ZVJhbmdlU2VsZWN0b3JPblNlbGVjdCxcblx0XHQpO1xuXHR9LFxuXHQvKipcblx0ICogSGFuZGxlcyB0aGUgZGF0ZSByYW5nZSBzZWxlY3RvciBzZWxlY3QgZXZlbnQuXG5cdCAqIEBwYXJhbSB7bW9tZW50Lk1vbWVudH0gc3RhcnQgLSBUaGUgc3RhcnQgZGF0ZS5cblx0ICogQHBhcmFtIHttb21lbnQuTW9tZW50fSBlbmQgLSBUaGUgZW5kIGRhdGUuXG5cdCAqIEBwYXJhbSB7c3RyaW5nfSBsYWJlbCAtIFRoZSBsYWJlbC5cblx0ICovXG5cdGNiRGF0ZVJhbmdlU2VsZWN0b3JPblNlbGVjdChzdGFydCwgZW5kLCBsYWJlbCkge1xuXHRcdE1vZHVsZUV4dGVuZGVkQ0RScy4kZGF0ZVJhbmdlU2VsZWN0b3IuYXR0cignZGF0YS1zdGFydCcsIHN0YXJ0LmZvcm1hdCgnWVlZWS9NTS9ERCcpKTtcblx0XHRNb2R1bGVFeHRlbmRlZENEUnMuJGRhdGVSYW5nZVNlbGVjdG9yLmF0dHIoJ2RhdGEtZW5kJywgbW9tZW50KGVuZC5mb3JtYXQoJ1lZWVlNTUREJykpLmVuZE9mKCdkYXknKS5mb3JtYXQoJ1lZWVkvTU0vREQnKSk7XG5cdFx0TW9kdWxlRXh0ZW5kZWRDRFJzLiRkYXRlUmFuZ2VTZWxlY3Rvci52YWwoYCR7c3RhcnQuZm9ybWF0KCdERC9NTS9ZWVlZJyl9IC0gJHtlbmQuZm9ybWF0KCdERC9NTS9ZWVlZJykgfWApO1xuXHRcdE1vZHVsZUV4dGVuZGVkQ0RScy5hcHBseUZpbHRlcigpO1xuXHR9LFxuXG5cdC8qKlxuXHQgKiBBcHBsaWVzIHRoZSBmaWx0ZXIgdG8gdGhlIGRhdGEgdGFibGUuXG5cdCAqL1xuXHRhcHBseUZpbHRlcigpIHtcblx0XHRjb25zdCB0ZXh0ICA9IE1vZHVsZUV4dGVuZGVkQ0RScy5nZXRTZWFyY2hUZXh0KCk7XG5cblx0XHRNb2R1bGVFeHRlbmRlZENEUnMuZGF0YVRhYmxlLnNlYXJjaCh0ZXh0KS5kcmF3KCk7XG5cdFx0TW9kdWxlRXh0ZW5kZWRDRFJzLiRnbG9iYWxTZWFyY2guY2xvc2VzdCgnZGl2JykuYWRkQ2xhc3MoJ2xvYWRpbmcnKTtcblx0fSxcblxuXHRnZXRTZWFyY2hUZXh0KCkge1xuXHRcdGNvbnN0IGZpbHRlciA9IHtcblx0XHRcdGRhdGVSYW5nZVNlbGVjdG9yOiBNb2R1bGVFeHRlbmRlZENEUnMuJGRhdGVSYW5nZVNlbGVjdG9yLnZhbCgpLFxuXHRcdFx0Z2xvYmFsU2VhcmNoOiBNb2R1bGVFeHRlbmRlZENEUnMuJGdsb2JhbFNlYXJjaC52YWwoKSxcblx0XHRcdHR5cGVDYWxsOiAkKCcjdHlwZUNhbGwgYS5pdGVtLmFjdGl2ZScpLmF0dHIoJ2RhdGEtdGFiJyksXG5cdFx0XHRhZGRpdGlvbmFsRmlsdGVyOiAkKCcjYWRkaXRpb25hbEZpbHRlcicpLmRyb3Bkb3duKCdnZXQgdmFsdWUnKS5yZXBsYWNlKC8sL2csJyAnKSxcblx0XHR9O1xuXHRcdHJldHVybiBKU09OLnN0cmluZ2lmeShmaWx0ZXIpO1xuXHR9LFxuXG5cdGdldFN0YW5kYXJkUGVyaW9kcygpe1xuXHRcdHJldHVybiB7XG5cdFx0XHQnY2FsX1RvZGF5JzogW21vbWVudCgpLCBtb21lbnQoKV0sXG5cdFx0XHQnY2FsX1llc3RlcmRheSc6IFttb21lbnQoKS5zdWJ0cmFjdCgxLCAnZGF5cycpLCBtb21lbnQoKS5zdWJ0cmFjdCgxLCAnZGF5cycpXSxcblx0XHRcdCdjYWxfTGFzdFdlZWsnOiBbbW9tZW50KCkuc3VidHJhY3QoNiwgJ2RheXMnKSwgbW9tZW50KCldLFxuXHRcdFx0J2NhbF9MYXN0MzBEYXlzJzogW21vbWVudCgpLnN1YnRyYWN0KDI5LCAnZGF5cycpLCBtb21lbnQoKV0sXG5cdFx0XHQnY2FsX1RoaXNNb250aCc6IFttb21lbnQoKS5zdGFydE9mKCdtb250aCcpLCBtb21lbnQoKS5lbmRPZignbW9udGgnKV0sXG5cdFx0XHQnY2FsX0xhc3RNb250aCc6IFttb21lbnQoKS5zdWJ0cmFjdCgxLCAnbW9udGgnKS5zdGFydE9mKCdtb250aCcpLCBtb21lbnQoKS5zdWJ0cmFjdCgxLCAnbW9udGgnKS5lbmRPZignbW9udGgnKV0sXG5cdFx0fVxuXHR9LFxuXG5cdHNhdmVTZWFyY2hTZXR0aW5ncygpIHtcblx0XHRsZXQgcGVyaW9kcyA9IE1vZHVsZUV4dGVuZGVkQ0RScy5nZXRTdGFuZGFyZFBlcmlvZHMoKTtcblx0XHRsZXQgZGF0ZVJhbmdlU2VsZWN0b3IgPSAnJztcblx0XHQkLmVhY2gocGVyaW9kcyxmdW5jdGlvbihpbmRleCx2YWx1ZSl7XG5cdFx0XHRpZihNb2R1bGVFeHRlbmRlZENEUnMuJGRhdGVSYW5nZVNlbGVjdG9yLnZhbCgpID09PSAgYCR7dmFsdWVbMF0uZm9ybWF0KCdERC9NTS9ZWVlZJyl9IC0gJHt2YWx1ZVsxXS5mb3JtYXQoJ0REL01NL1lZWVknKX1gKXtcblx0XHRcdFx0ZGF0ZVJhbmdlU2VsZWN0b3IgPSBpbmRleDtcblx0XHRcdH1cblx0XHR9KTtcblx0XHRjb25zdCBzZXR0aW5ncyA9IHtcblx0XHRcdCdhZGRpdGlvbmFsRmlsdGVyJyA6ICQoJyNhZGRpdGlvbmFsRmlsdGVyJykuZHJvcGRvd24oJ2dldCB2YWx1ZScpLnJlcGxhY2UoLywvZywnICcpLFxuXHRcdFx0J2RhdGVSYW5nZVNlbGVjdG9yJyA6IGRhdGVSYW5nZVNlbGVjdG9yXG5cdFx0fTtcblx0XHQkLmFqYXgoe1xuXHRcdFx0dXJsOiBgJHtnbG9iYWxSb290VXJsfSR7aWRVcmx9L3NhdmVTZWFyY2hTZXR0aW5nc2AsXG5cdFx0XHR0eXBlOiAnUE9TVCcsXG5cdFx0XHRoZWFkZXJzOiB7XG5cdFx0XHRcdCdDb250ZW50LVR5cGUnOiAnYXBwbGljYXRpb24veC13d3ctZm9ybS11cmxlbmNvZGVkOyBjaGFyc2V0PVVURi04Jyxcblx0XHRcdFx0J1gtUmVxdWVzdGVkLVdpdGgnOiAnWE1MSHR0cFJlcXVlc3QnLFxuXHRcdFx0fSxcblx0XHRcdGRhdGE6IHtcblx0XHRcdFx0J3NlYXJjaFt2YWx1ZV0nOiBKU09OLnN0cmluZ2lmeShzZXR0aW5ncyksXG5cdFx0XHR9LFxuXHRcdFx0c3VjY2VzczogZnVuY3Rpb24ocmVzcG9uc2UpIHtcblx0XHRcdFx0Y29uc29sZS5sb2cocmVzcG9uc2UpO1xuXHRcdFx0fSxcblx0XHRcdGVycm9yOiBmdW5jdGlvbih4aHIsIHN0YXR1cywgZXJyb3IpIHtcblx0XHRcdFx0Y29uc29sZS5lcnJvcihlcnJvcik7XG5cdFx0XHR9XG5cdFx0fSk7XG5cdH0sXG5cblx0c3RhcnRDcmVhdGVFeGNlbCgpIHtcblx0XHRjb25zdCB0ZXh0ICA9IE1vZHVsZUV4dGVuZGVkQ0RScy5nZXRTZWFyY2hUZXh0KCk7XG5cblx0XHQkLmFqYXgoe1xuXHRcdFx0dXJsOiBgJHtnbG9iYWxSb290VXJsfSR7aWRVcmx9L2dldEhpc3RvcnlgLFxuXHRcdFx0dHlwZTogJ1BPU1QnLFxuXHRcdFx0aGVhZGVyczoge1xuXHRcdFx0XHQnQ29udGVudC1UeXBlJzogJ2FwcGxpY2F0aW9uL3gtd3d3LWZvcm0tdXJsZW5jb2RlZDsgY2hhcnNldD1VVEYtOCcsXG5cdFx0XHRcdCdYLVJlcXVlc3RlZC1XaXRoJzogJ1hNTEh0dHBSZXF1ZXN0Jyxcblx0XHRcdH0sXG5cdFx0XHRkYXRhOiB7XG5cdFx0XHRcdCdzZWFyY2hbdmFsdWVdJzogdGV4dCxcblx0XHRcdH0sXG5cdFx0XHRzdWNjZXNzOiBmdW5jdGlvbihyZXNwb25zZSkge1xuXHRcdFx0XHRjb25zdCBmbGF0dGVuZWREYXRhID0gW1xuXHRcdFx0XHRcdHtcblx0XHRcdFx0XHRcdHN0YXJ0OiAnJyxcblx0XHRcdFx0XHRcdHNyY19udW06ICcnLFxuXHRcdFx0XHRcdFx0ZHN0X251bTogJycsXG5cdFx0XHRcdFx0XHRiaWxsc2VjOiAnJyxcblx0XHRcdFx0XHRcdHN0YXRlQ2FsbDogJycsXG5cdFx0XHRcdFx0XHR0eXBlQ2FsbDogJycsXG5cdFx0XHRcdFx0XHRsaW5lOiAnJyxcblx0XHRcdFx0XHRcdHdhaXRUaW1lOiAnJyxcblx0XHRcdFx0XHR9XG5cdFx0XHRcdF07XG5cblx0XHRcdFx0JC5lYWNoKHJlc3BvbnNlLmRhdGEsIGZ1bmN0aW9uKGluZGV4LCBpdGVtKSB7XG5cdFx0XHRcdFx0Y29uc3QgYmFzZVJlY29yZCA9IHtcblx0XHRcdFx0XHRcdHN0YXJ0OiBpdGVtWycwJ10sXG5cdFx0XHRcdFx0XHRzcmNfbnVtOiBpdGVtWycxJ10sXG5cdFx0XHRcdFx0XHRkc3RfbnVtOiBpdGVtWycyJ10sXG5cdFx0XHRcdFx0XHRiaWxsc2VjOiBpdGVtWyczJ10sXG5cdFx0XHRcdFx0XHRzdGF0ZUNhbGw6IGl0ZW1bJ3N0YXRlQ2FsbCddLFxuXHRcdFx0XHRcdFx0dHlwZUNhbGw6IGl0ZW1bJ3R5cGVDYWxsRGVzYyddLFxuXHRcdFx0XHRcdFx0bGluZTogaXRlbVsnbGluZSddLFxuXHRcdFx0XHRcdFx0d2FpdFRpbWU6IGl0ZW1bJ3dhaXRUaW1lJ10sXG5cdFx0XHRcdFx0fTtcblxuXHRcdFx0XHRcdC8vINCU0L7QsdCw0LLQu9GP0LXQvCDQvtGB0L3QvtCy0L3QvtC5INGN0LvQtdC80LXQvdGCXG5cdFx0XHRcdFx0ZmxhdHRlbmVkRGF0YS5wdXNoKGJhc2VSZWNvcmQpO1xuXG5cdFx0XHRcdFx0Ly8g0JXRgdC70Lgg0LXRgdGC0Ywg0LLQu9C+0LbQtdC90L3Ri9C1INC00LDQvdC90YvQtSwg0LTQvtCx0LDQstC70Y/QtdC8INC40YUg0YHRgNCw0LfRgyDQv9C+0YHQu9C1INC+0YHQvdC+0LLQvdC+0LPQviDRjdC70LXQvNC10L3RgtCwXG5cdFx0XHRcdFx0aWYgKGl0ZW1bJzQnXSAmJiBpdGVtWyc0J10ubGVuZ3RoID4gMCkge1xuXHRcdFx0XHRcdFx0JC5lYWNoKGl0ZW1bJzQnXSwgZnVuY3Rpb24oaSwgbmVzdGVkSXRlbSkge1xuXHRcdFx0XHRcdFx0XHQvLyDQn9GA0L7QstC10YDRj9C10LwsINGB0L7QstC/0LDQtNCw0Y7RgiDQu9C4INC30L3QsNGH0LXQvdC40Y8gc3RhcnQsIHNyY19udW0g0LggZHN0X251bSDRgSDQvtGB0L3QvtCy0L3QvtC5INC30LDQv9C40YHRjNGOXG5cdFx0XHRcdFx0XHRcdGlmIChuZXN0ZWRJdGVtLnN0YXJ0ICE9PSBpdGVtWycwJ10gfHwgbmVzdGVkSXRlbS5zcmNfbnVtICE9PSBpdGVtWycxJ10gfHwgbmVzdGVkSXRlbS5kc3RfbnVtICE9PSBpdGVtWycyJ10pIHtcblx0XHRcdFx0XHRcdFx0XHRmbGF0dGVuZWREYXRhLnB1c2goe1xuXHRcdFx0XHRcdFx0XHRcdFx0c3RhcnQ6IG5lc3RlZEl0ZW0uc3RhcnQgfHwgaXRlbVsnMCddLFxuXHRcdFx0XHRcdFx0XHRcdFx0c3JjX251bTogbmVzdGVkSXRlbS5zcmNfbnVtIHx8IGl0ZW1bJzEnXSxcblx0XHRcdFx0XHRcdFx0XHRcdGRzdF9udW06IG5lc3RlZEl0ZW0uZHN0X251bSB8fCBpdGVtWycyJ10sXG5cdFx0XHRcdFx0XHRcdFx0XHRiaWxsc2VjOiBuZXN0ZWRJdGVtLmJpbGxzZWMgfHwgaXRlbVsnMyddLFxuXHRcdFx0XHRcdFx0XHRcdFx0c3RhdGVDYWxsOiBuZXN0ZWRJdGVtLnN0YXRlQ2FsbCxcblx0XHRcdFx0XHRcdFx0XHRcdHR5cGVDYWxsOiBpdGVtWyd0eXBlQ2FsbERlc2MnXSxcblx0XHRcdFx0XHRcdFx0XHRcdGxpbmU6IGl0ZW1bJ2xpbmUnXSxcblx0XHRcdFx0XHRcdFx0XHRcdHdhaXRUaW1lOiBuZXN0ZWRJdGVtLndhaXRUaW1lXG5cdFx0XHRcdFx0XHRcdFx0XHQvLyDQlNGA0YPQs9C40LUg0YHQstC+0LnRgdGC0LLQsCDQvNC+0LbQvdC+INC00L7QsdCw0LLQuNGC0Ywg0LfQtNC10YHRjFxuXHRcdFx0XHRcdFx0XHRcdH0pO1xuXHRcdFx0XHRcdFx0XHR9XG5cdFx0XHRcdFx0XHR9KTtcblx0XHRcdFx0XHR9XG5cdFx0XHRcdH0pO1xuXG5cdFx0XHRcdGxldCBjb2x1bW5zID0gW1xuXHRcdFx0XHRcdFwidHlwZUNhbGxcIixcblx0XHRcdFx0XHRcInN0YXJ0XCIsXG5cdFx0XHRcdFx0XCJzcmNfbnVtXCIsXG5cdFx0XHRcdFx0XCJkc3RfbnVtXCIsXG5cdFx0XHRcdFx0XCJsaW5lXCIsXG5cdFx0XHRcdFx0XCJ3YWl0VGltZVwiLFxuXHRcdFx0XHRcdFwiYmlsbHNlY1wiLFxuXHRcdFx0XHRcdFwic3RhdGVDYWxsXCJcblx0XHRcdFx0XTtcblx0XHRcdFx0Y29uc3Qgd29ya3NoZWV0ID0gWExTWC51dGlscy5qc29uX3RvX3NoZWV0KGZsYXR0ZW5lZERhdGEse1xuXHRcdFx0XHRcdGhlYWRlcjogY29sdW1ucyxcblx0XHRcdFx0XHRza2lwSGVhZGVyOiB0cnVlICAvLyDQn9GA0L7Qv9GD0YHQutCw0LXQvCDQsNCy0YLQvtC80LDRgtC40YfQtdGB0LrQvtC1INGB0L7Qt9C00LDQvdC40LUg0LfQsNCz0L7Qu9C+0LLQutC+0LIg0LjQtyDQutC70Y7Rh9C10Lkg0L7QsdGK0LXQutGC0LBcblx0XHRcdFx0fSk7XG5cdFx0XHRcdFhMU1gudXRpbHMuc2hlZXRfYWRkX2FvYSh3b3Jrc2hlZXQsIFtbXG5cdFx0XHRcdFx0Z2xvYmFsVHJhbnNsYXRlLnJlcE1vZHVsZUV4dGVuZGVkQ0RSc19jZHJfQ29sdW1uVHlwZVN0YXRlLFxuXHRcdFx0XHRcdGdsb2JhbFRyYW5zbGF0ZS5jZHJfQ29sdW1uRGF0ZSxcblx0XHRcdFx0XHRnbG9iYWxUcmFuc2xhdGUuY2RyX0NvbHVtbkZyb20sXG5cdFx0XHRcdFx0Z2xvYmFsVHJhbnNsYXRlLmNkcl9Db2x1bW5Ubyxcblx0XHRcdFx0XHRnbG9iYWxUcmFuc2xhdGUucmVwTW9kdWxlRXh0ZW5kZWRDRFJzX2Nkcl9Db2x1bW5MaW5lLFxuXHRcdFx0XHRcdGdsb2JhbFRyYW5zbGF0ZS5yZXBNb2R1bGVFeHRlbmRlZENEUnNfY2RyX0NvbHVtbldhaXRUaW1lLFxuXHRcdFx0XHRcdGdsb2JhbFRyYW5zbGF0ZS5jZHJfQ29sdW1uRHVyYXRpb24sXG5cdFx0XHRcdFx0Z2xvYmFsVHJhbnNsYXRlLnJlcE1vZHVsZUV4dGVuZGVkQ0RSc19jZHJfQ29sdW1uQ2FsbFN0YXRlLFxuXHRcdFx0XHRdXSwge29yaWdpbjogXCJBMVwifSk7XG5cblx0XHRcdFx0d29ya3NoZWV0WychY29scyddID0gIGNvbHVtbnMubWFwKGNvbCA9PiAoe1xuXHRcdFx0XHRcdHdjaDogOCArIE1vZHVsZUV4dGVuZGVkQ0RScy5nZXRNYXhXaWR0aChmbGF0dGVuZWREYXRhLCBjb2wpXG5cdFx0XHRcdH0pKTtcblxuXHRcdFx0XHRjb25zdCB3b3JrYm9vayA9IFhMU1gudXRpbHMuYm9va19uZXcoKTtcblx0XHRcdFx0WExTWC51dGlscy5ib29rX2FwcGVuZF9zaGVldCh3b3JrYm9vaywgd29ya3NoZWV0LCBcImNkclwiKTtcblx0XHRcdFx0WExTWC53cml0ZUZpbGUod29ya2Jvb2ssIFwiaGlzdG9yeS54bHN4XCIpO1xuXHRcdFx0fSxcblx0XHRcdGVycm9yOiBmdW5jdGlvbih4aHIsIHN0YXR1cywgZXJyb3IpIHtcblx0XHRcdFx0Y29uc29sZS5lcnJvcihlcnJvcik7XG5cdFx0XHR9XG5cdFx0fSk7XG5cdH0sXG5cblx0Z2V0TWF4V2lkdGgoZGF0YSwga2V5KSB7XG5cdFx0Ly8g0J/QvtC70YPRh9Cw0LXQvCDQvNCw0LrRgdC40LzQsNC70YzQvdGD0Y4g0LTQu9C40L3RgyDRgdC+0LTQtdGA0LbQuNC80L7Qs9C+INCyINGB0YLQvtC70LHRhtC1XG5cdFx0bGV0IG1heExlbmd0aCA9IGtleS5sZW5ndGg7IC8vINC90LDRh9C40L3QsNC10Lwg0YEg0LTQu9C40L3RiyDQt9Cw0LPQvtC70L7QstC60LBcblx0XHRkYXRhLmZvckVhY2gocm93ID0+IHtcblx0XHRcdGxldCBsZW5ndGggPSAocm93W2tleV0gfHwgJycpLnRvU3RyaW5nKCkubGVuZ3RoO1xuXHRcdFx0aWYgKGxlbmd0aCA+IG1heExlbmd0aCkgbWF4TGVuZ3RoID0gbGVuZ3RoO1xuXHRcdH0pO1xuXHRcdHJldHVybiBtYXhMZW5ndGg7XG5cdH0sXG5cblx0c3RhcnREb3dubG9hZCgpe1xuXHRcdGxldCBzdGFydFRpbWUgPSBNb2R1bGVFeHRlbmRlZENEUnMuJGRhdGVSYW5nZVNlbGVjdG9yLmF0dHIoJ2RhdGEtc3RhcnQnKTtcblx0XHRsZXQgZW5kVGltZSAgID0gTW9kdWxlRXh0ZW5kZWRDRFJzLiRkYXRlUmFuZ2VTZWxlY3Rvci5hdHRyKCdkYXRhLWVuZCcpO1xuXHRcdGlmKHN0YXJ0VGltZSA9PT0gdW5kZWZpbmVkKXtcblx0XHRcdHN0YXJ0VGltZSA9IG1vbWVudCgpLmZvcm1hdCgnWVlZWS1NTS1ERCcpO1xuXHRcdFx0ZW5kVGltZSAgID0gIG1vbWVudCgpLmVuZE9mKCdkYXknKS5mb3JtYXQoJ1lZWVktTU0tREQgSEg6bW06c3MnKVxuXHRcdH1cblx0XHRsZXQgdHlwZVJlYyA9ICdpbm5lcic7XG5cdFx0aWYoJCgnI2FsbFJlY29yZCcpLmNoZWNrYm94KCdpcyBjaGVja2VkJykpe1xuXHRcdFx0dHlwZVJlYyA9ICdhbGwnO1xuXHRcdH1lbHNlIGlmKCQoJyNvdXRSZWNvcmQnKS5jaGVja2JveCgnaXMgY2hlY2tlZCcpKXtcblx0XHRcdHR5cGVSZWMgPSAnb3V0Jztcblx0XHR9XG5cdFx0bGV0IG51bWJlcnMgPSBNb2R1bGVFeHRlbmRlZENEUnMuJGdsb2JhbFNlYXJjaC52YWwoKTtcblx0XHR3aW5kb3cub3BlbignL3BieGNvcmUvYXBpL21vZHVsZXMvJytjbGFzc05hbWUrJy9kb3dubG9hZHM/c3RhcnQ9JytzdGFydFRpbWUrJyZlbmQ9JytlbmRUaW1lK1wiJm51bWJlcnM9XCIrZW5jb2RlVVJJQ29tcG9uZW50KG51bWJlcnMpK1wiJnR5cGU9XCIrdHlwZVJlYywgJ19ibGFuaycpO1xuXHR9LFxuXG5cdHN0YXJ0RG93bmxvYWRIaXN0b3J5KCl7XG5cdFx0bGV0IHN0YXJ0VGltZSA9IE1vZHVsZUV4dGVuZGVkQ0RScy4kZGF0ZVJhbmdlU2VsZWN0b3IuYXR0cignZGF0YS1zdGFydCcpO1xuXHRcdGxldCBlbmRUaW1lICAgPSBNb2R1bGVFeHRlbmRlZENEUnMuJGRhdGVSYW5nZVNlbGVjdG9yLmF0dHIoJ2RhdGEtZW5kJyk7XG5cdFx0aWYoc3RhcnRUaW1lID09PSB1bmRlZmluZWQpe1xuXHRcdFx0c3RhcnRUaW1lID0gbW9tZW50KCkuZm9ybWF0KCdZWVlZLU1NLUREJyk7XG5cdFx0XHRlbmRUaW1lID0gIG1vbWVudCgpLmVuZE9mKCdkYXknKS5mb3JtYXQoJ1lZWVktTU0tREQgSEg6bW06c3MnKVxuXHRcdH1cblx0XHRsZXQgdHlwZVJlYyA9ICdpbm5lcic7XG5cdFx0aWYoJCgnI2FsbFJlY29yZCcpLmNoZWNrYm94KCdpcyBjaGVja2VkJykpe1xuXHRcdFx0dHlwZVJlYyA9ICdhbGwnO1xuXHRcdH1lbHNlIGlmKCQoJyNvdXRSZWNvcmQnKS5jaGVja2JveCgnaXMgY2hlY2tlZCcpKXtcblx0XHRcdHR5cGVSZWMgPSAnb3V0Jztcblx0XHR9XG5cdFx0bGV0IG51bWJlcnMgPSBNb2R1bGVFeHRlbmRlZENEUnMuJGdsb2JhbFNlYXJjaC52YWwoKTtcblx0XHR3aW5kb3cub3BlbignL3BieGNvcmUvYXBpL21vZHVsZXMvJytjbGFzc05hbWUrJy9kb3dubG9hZHMtaGlzdG9yeT9zdGFydD0nK3N0YXJ0VGltZSsnJmVuZD0nK2VuZFRpbWUrXCImbnVtYmVycz1cIitlbmNvZGVVUklDb21wb25lbnQobnVtYmVycykrXCImdHlwZT1cIit0eXBlUmVjLCAnX2JsYW5rJyk7XG5cdH0sXG5cblx0LyoqXG5cdCAqINCf0L7QtNCz0L7RgtCw0LLQu9C40LLQsNC10YIg0YHQv9C40YHQvtC6INCy0YvQsdC+0YDQsFxuXHQgKiBAcGFyYW0gc2VsZWN0ZWRcblx0ICogQHJldHVybnMge1tdfVxuXHQgKi9cblx0bWFrZURyb3Bkb3duTGlzdChzZWxlY3RUeXBlLCBzZWxlY3RlZCkge1xuXHRcdGNvbnN0IHZhbHVlcyA9IFtcblx0XHRcdHtcblx0XHRcdFx0bmFtZTogJyAtLS0gJyxcblx0XHRcdFx0dmFsdWU6ICcnLFxuXHRcdFx0XHRzZWxlY3RlZDogKCcnID09PSBzZWxlY3RlZCksXG5cdFx0XHR9XG5cdFx0XTtcblx0XHQkKCcjJytzZWxlY3RUeXBlKycgb3B0aW9uJykuZWFjaCgoaW5kZXgsIG9iaikgPT4ge1xuXHRcdFx0dmFsdWVzLnB1c2goe1xuXHRcdFx0XHRuYW1lOiBvYmoudGV4dCxcblx0XHRcdFx0dmFsdWU6IG9iai52YWx1ZSxcblx0XHRcdFx0c2VsZWN0ZWQ6IChzZWxlY3RlZCA9PT0gb2JqLnZhbHVlKSxcblx0XHRcdH0pO1xuXHRcdH0pO1xuXHRcdHJldHVybiB2YWx1ZXM7XG5cdH0sXG5cblx0LyoqXG5cdCAqINCe0LHRgNCw0LHQvtGC0LrQsCDQuNC30LzQtdC90LXQvdC40Y8g0LPRgNGD0L/Qv9GLINCyINGB0L/QuNGB0LrQtVxuXHQgKi9cblx0Y2hhbmdlR3JvdXBJbkxpc3QodmFsdWUsIHRleHQsIGNob2ljZSkge1xuXHRcdGxldCB0ZElucHV0ID0gJChjaG9pY2UpLmNsb3Nlc3QoJ3RkJykuZmluZCgnaW5wdXQnKTtcblx0XHR0ZElucHV0LmF0dHIoJ2RhdGEtdmFsdWUnLCBcdHZhbHVlKTtcblx0XHR0ZElucHV0LmF0dHIoJ3ZhbHVlJywgXHRcdHZhbHVlKTtcblx0XHRsZXQgY3VycmVudFJvd0lkID0gJChjaG9pY2UpLmNsb3Nlc3QoJ3RyJykuYXR0cignaWQnKTtcblx0XHRsZXQgdGFibGVOYW1lICAgID0gJChjaG9pY2UpLmNsb3Nlc3QoJ3RhYmxlJykuYXR0cignaWQnKS5yZXBsYWNlKCctdGFibGUnLCAnJyk7XG5cdFx0aWYgKGN1cnJlbnRSb3dJZCAhPT0gdW5kZWZpbmVkICYmIHRhYmxlTmFtZSAhPT0gdW5kZWZpbmVkKSB7XG5cdFx0XHR3aW5kb3dbY2xhc3NOYW1lXS5zZW5kQ2hhbmdlc1RvU2VydmVyKHRhYmxlTmFtZSwgY3VycmVudFJvd0lkKTtcblx0XHR9XG5cdH0sXG5cblx0LyoqXG5cdCAqIEFkZCBuZXcgVGFibGUuXG5cdCAqL1xuXHRpbml0VGFibGUodGFibGVOYW1lLCBvcHRpb25zKSB7XG5cdFx0bGV0IGNvbHVtbnMgPSBbXTtcblx0XHRsZXQgY29sdW1uc0FycmF5NFNvcnQgPSBbXVxuXHRcdGZvciAobGV0IGNvbE5hbWUgaW4gb3B0aW9uc1snY29scyddKSB7XG5cdFx0XHRjb2x1bW5zLnB1c2goIHtkYXRhOiBjb2xOYW1lfSk7XG5cdFx0XHRjb2x1bW5zQXJyYXk0U29ydC5wdXNoKGNvbE5hbWUpO1xuXHRcdH1cblx0XHQkKCcjJyArIHRhYmxlTmFtZSkuRGF0YVRhYmxlKCB7XG5cdFx0XHRhamF4OiB7XG5cdFx0XHRcdHVybDogaWRVcmwgKyBvcHRpb25zLmFqYXhVcmwgKyAnP3RhYmxlPScgK3RhYmxlTmFtZS5yZXBsYWNlKCctdGFibGUnLCAnJyksXG5cdFx0XHRcdGRhdGFTcmM6ICdkYXRhJ1xuXHRcdFx0fSxcblx0XHRcdGNvbHVtbnM6IGNvbHVtbnMsXG5cdFx0XHRwYWdpbmc6IHRydWUsXG5cdFx0XHRzRG9tOiAncnRpcCcsXG5cdFx0XHRkZWZlclJlbmRlcjogdHJ1ZSxcblx0XHRcdHBhZ2VMZW5ndGg6IDE3LFxuXHRcdFx0aW5mb0NhbGxiYWNrKCBzZXR0aW5ncywgc3RhcnQsIGVuZCwgbWF4LCB0b3RhbCwgcHJlICkge1xuXHRcdFx0XHRyZXR1cm4gJyc7XG5cdFx0XHR9LFxuXHRcdFx0bGFuZ3VhZ2U6IFNlbWFudGljTG9jYWxpemF0aW9uLmRhdGFUYWJsZUxvY2FsaXNhdGlvbixcblx0XHRcdG9yZGVyaW5nOiBmYWxzZSxcblx0XHRcdC8qKlxuXHRcdFx0ICogQnVpbGRlciByb3cgcHJlc2VudGF0aW9uXG5cdFx0XHQgKiBAcGFyYW0gcm93XG5cdFx0XHQgKiBAcGFyYW0gZGF0YVxuXHRcdFx0ICovXG5cdFx0XHRjcmVhdGVkUm93KHJvdywgZGF0YSkge1xuXHRcdFx0XHRsZXQgY29scyAgICA9ICQoJ3RkJywgcm93KTtcblx0XHRcdFx0bGV0IGhlYWRlcnMgPSAkKCcjJysgdGFibGVOYW1lICsgJyB0aGVhZCB0ciB0aCcpO1xuXHRcdFx0XHRmb3IgKGxldCBrZXkgaW4gZGF0YSkge1xuXHRcdFx0XHRcdGxldCBpbmRleCA9IGNvbHVtbnNBcnJheTRTb3J0LmluZGV4T2Yoa2V5KTtcblx0XHRcdFx0XHRpZihrZXkgPT09ICdyb3dJY29uJyl7XG5cdFx0XHRcdFx0XHRjb2xzLmVxKGluZGV4KS5odG1sKCc8aSBjbGFzcz1cInVpICcgKyBkYXRhW2tleV0gKyAnIGNpcmNsZSBpY29uXCI+PC9pPicpO1xuXHRcdFx0XHRcdH1lbHNlIGlmKGtleSA9PT0gJ2RlbEJ1dHRvbicpe1xuXHRcdFx0XHRcdFx0bGV0IHRlbXBsYXRlRGVsZXRlQnV0dG9uID0gJzxkaXYgY2xhc3M9XCJ1aSBzbWFsbCBiYXNpYyBpY29uIGJ1dHRvbnMgYWN0aW9uLWJ1dHRvbnNcIj4nICtcblx0XHRcdFx0XHRcdFx0JzxhIGhyZWY9XCInICsgd2luZG93W2NsYXNzTmFtZV0uZGVsZXRlUmVjb3JkQUpBWFVybCArICcvJyArXG5cdFx0XHRcdFx0XHRcdGRhdGEuaWQgKyAnXCIgZGF0YS12YWx1ZSA9IFwiJyArIGRhdGEuRFRfUm93SWQgKyAnXCInICtcblx0XHRcdFx0XHRcdFx0JyBjbGFzcz1cInVpIGJ1dHRvbiBkZWxldGUgdHdvLXN0ZXBzLWRlbGV0ZSBwb3B1cGVkXCIgZGF0YS1jb250ZW50PVwiJyArIGdsb2JhbFRyYW5zbGF0ZS5idF9Ub29sVGlwRGVsZXRlICsgJ1wiPicgK1xuXHRcdFx0XHRcdFx0XHQnPGkgY2xhc3M9XCJpY29uIHRyYXNoIHJlZFwiPjwvaT48L2E+PC9kaXY+Jztcblx0XHRcdFx0XHRcdGNvbHMuZXEoaW5kZXgpLmh0bWwodGVtcGxhdGVEZWxldGVCdXR0b24pO1xuXHRcdFx0XHRcdH1lbHNlIGlmKGtleSA9PT0gJ3ByaW9yaXR5Jyl7XG5cdFx0XHRcdFx0XHRjb2xzLmVxKGluZGV4KS5hZGRDbGFzcygnZHJhZ0hhbmRsZScpXG5cdFx0XHRcdFx0XHRjb2xzLmVxKGluZGV4KS5odG1sKCc8aSBjbGFzcz1cInVpIHNvcnQgY2lyY2xlIGljb25cIj48L2k+Jyk7XG5cdFx0XHRcdFx0XHQvLyDQn9GA0LjQvtGA0LjRgtC10YIg0YPRgdGC0LDQvdCw0LLQu9C40LLQsNC10Lwg0LTQu9GPINGB0YLRgNC+0LrQuC5cblx0XHRcdFx0XHRcdCQocm93KS5hdHRyKCdtLXByaW9yaXR5JywgZGF0YVtrZXldKTtcblx0XHRcdFx0XHR9ZWxzZXtcblx0XHRcdFx0XHRcdGxldCB0ZW1wbGF0ZSA9ICc8ZGl2IGNsYXNzPVwidWkgdHJhbnNwYXJlbnQgZmx1aWQgaW5wdXQgaW5saW5lLWVkaXRcIj4nICtcblx0XHRcdFx0XHRcdFx0JzxpbnB1dCBjb2xOYW1lPVwiJytrZXkrJ1wiIGNsYXNzPVwiJytpbnB1dENsYXNzTmFtZSsnXCIgdHlwZT1cInRleHRcIiBkYXRhLXZhbHVlPVwiJytkYXRhW2tleV0gKyAnXCIgdmFsdWU9XCInICsgZGF0YVtrZXldICsgJ1wiPjwvZGl2Pic7XG5cdFx0XHRcdFx0XHQkKCd0ZCcsIHJvdykuZXEoaW5kZXgpLmh0bWwodGVtcGxhdGUpO1xuXHRcdFx0XHRcdH1cblx0XHRcdFx0XHRpZihvcHRpb25zWydjb2xzJ11ba2V5XSA9PT0gdW5kZWZpbmVkKXtcblx0XHRcdFx0XHRcdGNvbnRpbnVlO1xuXHRcdFx0XHRcdH1cblx0XHRcdFx0XHRsZXQgYWRkaXRpb25hbENsYXNzID0gb3B0aW9uc1snY29scyddW2tleV1bJ2NsYXNzJ107XG5cdFx0XHRcdFx0aWYoYWRkaXRpb25hbENsYXNzICE9PSB1bmRlZmluZWQgJiYgYWRkaXRpb25hbENsYXNzICE9PSAnJyl7XG5cdFx0XHRcdFx0XHRoZWFkZXJzLmVxKGluZGV4KS5hZGRDbGFzcyhhZGRpdGlvbmFsQ2xhc3MpO1xuXHRcdFx0XHRcdH1cblx0XHRcdFx0XHRsZXQgaGVhZGVyID0gb3B0aW9uc1snY29scyddW2tleV1bJ2hlYWRlciddO1xuXHRcdFx0XHRcdGlmKGhlYWRlciAhPT0gdW5kZWZpbmVkICYmIGhlYWRlciAhPT0gJycpe1xuXHRcdFx0XHRcdFx0aGVhZGVycy5lcShpbmRleCkuaHRtbChoZWFkZXIpO1xuXHRcdFx0XHRcdH1cblxuXHRcdFx0XHRcdGxldCBzZWxlY3RNZXRhRGF0YSA9IG9wdGlvbnNbJ2NvbHMnXVtrZXldWydzZWxlY3QnXTtcblx0XHRcdFx0XHRpZihzZWxlY3RNZXRhRGF0YSAhPT0gdW5kZWZpbmVkKXtcblx0XHRcdFx0XHRcdGxldCBuZXdUZW1wbGF0ZSA9ICQoJyN0ZW1wbGF0ZS1zZWxlY3QnKS5odG1sKCkucmVwbGFjZSgnUEFSQU0nLCBkYXRhW2tleV0pO1xuXHRcdFx0XHRcdFx0bGV0IHRlbXBsYXRlID0gJzxpbnB1dCBjbGFzcz1cIicraW5wdXRDbGFzc05hbWUrJ1wiIGNvbE5hbWU9XCInK2tleSsnXCIgc2VsZWN0VHlwZT1cIicrc2VsZWN0TWV0YURhdGErJ1wiIHN0eWxlPVwiZGlzcGxheTogbm9uZTtcIiB0eXBlPVwidGV4dFwiIGRhdGEtdmFsdWU9XCInK2RhdGFba2V5XSArICdcIiB2YWx1ZT1cIicgKyBkYXRhW2tleV0gKyAnXCI+PC9kaXY+Jztcblx0XHRcdFx0XHRcdGNvbHMuZXEoaW5kZXgpLmh0bWwobmV3VGVtcGxhdGUgKyB0ZW1wbGF0ZSk7XG5cdFx0XHRcdFx0fVxuXHRcdFx0XHR9XG5cdFx0XHR9LFxuXHRcdFx0LyoqXG5cdFx0XHQgKiBEcmF3IGV2ZW50IC0gZmlyZWQgb25jZSB0aGUgdGFibGUgaGFzIGNvbXBsZXRlZCBhIGRyYXcuXG5cdFx0XHQgKi9cblx0XHRcdGRyYXdDYWxsYmFjayhzZXR0aW5ncykge1xuXHRcdFx0XHR3aW5kb3dbY2xhc3NOYW1lXS5kcm93U2VsZWN0R3JvdXAoc2V0dGluZ3Muc1RhYmxlSWQpO1xuXHRcdFx0fSxcblx0XHR9ICk7XG5cblx0XHRsZXQgYm9keSA9ICQoJ2JvZHknKTtcblx0XHQvLyDQmtC70LjQuiDQv9C+INC/0L7Qu9GOLiDQktGF0L7QtCDQtNC70Y8g0YDQtdC00LDQutGC0LjRgNC+0LLQsNC90LjRjyDQt9C90LDRh9C10L3QuNGPLlxuXHRcdGJvZHkub24oJ2ZvY3VzaW4nLCAnLicraW5wdXRDbGFzc05hbWUsIGZ1bmN0aW9uIChlKSB7XG5cdFx0XHQkKGUudGFyZ2V0KS50cmFuc2l0aW9uKCdnbG93Jyk7XG5cdFx0XHQkKGUudGFyZ2V0KS5jbG9zZXN0KCdkaXYnKS5yZW1vdmVDbGFzcygndHJhbnNwYXJlbnQnKS5hZGRDbGFzcygnY2hhbmdlZC1maWVsZCcpO1xuXHRcdFx0JChlLnRhcmdldCkuYXR0cigncmVhZG9ubHknLCBmYWxzZSk7XG5cdFx0fSlcblx0XHQvLyDQntGC0L/RgNCw0LLQutCwINGE0L7RgNC80Ysg0L3QsCDRgdC10YDQstC10YAg0L/QviBFbnRlciDQuNC70LggVGFiXG5cdFx0JChkb2N1bWVudCkub24oJ2tleWRvd24nLCBmdW5jdGlvbiAoZSkge1xuXHRcdFx0bGV0IGtleUNvZGUgPSBlLmtleUNvZGUgfHwgZS53aGljaDtcblx0XHRcdGlmIChrZXlDb2RlID09PSAxMyB8fCBrZXlDb2RlID09PSA5ICYmICQoJzpmb2N1cycpLmhhc0NsYXNzKCdtaWtvcGJ4LW1vZHVsZS1pbnB1dCcpKSB7XG5cdFx0XHRcdHdpbmRvd1tjbGFzc05hbWVdLmVuZEVkaXRJbnB1dCgpO1xuXHRcdFx0fVxuXHRcdH0pO1xuXG5cdFx0Ym9keS5vbignY2xpY2snLCAnYS5kZWxldGUnLCBmdW5jdGlvbiAoZSkge1xuXHRcdFx0ZS5wcmV2ZW50RGVmYXVsdCgpO1xuXHRcdFx0bGV0IGN1cnJlbnRSb3dJZCA9ICQoZS50YXJnZXQpLmNsb3Nlc3QoJ3RyJykuYXR0cignaWQnKTtcblx0XHRcdGxldCB0YWJsZU5hbWUgICAgPSAkKGUudGFyZ2V0KS5jbG9zZXN0KCd0YWJsZScpLmF0dHIoJ2lkJykucmVwbGFjZSgnLXRhYmxlJywgJycpO1xuXHRcdFx0d2luZG93W2NsYXNzTmFtZV0uZGVsZXRlUm93KHRhYmxlTmFtZSwgY3VycmVudFJvd0lkKTtcblx0XHR9KTsgLy8g0JTQvtCx0LDQstC70LXQvdC40LUg0L3QvtCy0L7QuSDRgdGC0YDQvtC60LhcblxuXHRcdC8vINCe0YLQv9GA0LDQstC60LAg0YTQvtGA0LzRiyDQvdCwINGB0LXRgNCy0LXRgCDQv9C+INGD0YXQvtC00YMg0YEg0L/QvtC70Y8g0LLQstC+0LTQsFxuXHRcdGJvZHkub24oJ2ZvY3Vzb3V0JywgJy4nK2lucHV0Q2xhc3NOYW1lLCB3aW5kb3dbY2xhc3NOYW1lXS5lbmRFZGl0SW5wdXQpO1xuXG5cdFx0Ly8g0JrQvdC+0L/QutCwIFwi0JTQvtCx0LDQstC40YLRjCDQvdC+0LLRg9GOINC30LDQv9C40YHRjFwiXG5cdFx0JCgnW2lkLXRhYmxlID0gXCInK3RhYmxlTmFtZSsnXCJdJykub24oJ2NsaWNrJywgd2luZG93W2NsYXNzTmFtZV0uYWRkTmV3Um93KTtcblx0fSxcblxuXHQvKipcblx0ICog0J/QtdGA0LXQvNC10YnQtdC90LjQtSDRgdGC0YDQvtC60LgsINC40LfQvNC10L3QtdC90LjQtSDQv9GA0LjQvtGA0LjRgtC10YLQsC5cblx0ICovXG5cdGNiT25Ecm9wKHRhYmxlLCByb3cpIHtcblx0XHRsZXQgcHJpb3JpdHlXYXNDaGFuZ2VkID0gZmFsc2U7XG5cdFx0Y29uc3QgcHJpb3JpdHlEYXRhID0ge307XG5cdFx0JCh0YWJsZSkuZmluZCgndHInKS5lYWNoKChpbmRleCwgb2JqKSA9PiB7XG5cdFx0XHRjb25zdCBydWxlSWQgPSAkKG9iaikuYXR0cignaWQnKTtcblx0XHRcdGNvbnN0IG9sZFByaW9yaXR5ID0gcGFyc2VJbnQoJChvYmopLmF0dHIoJ20tcHJpb3JpdHknKSwgMTApO1xuXHRcdFx0Y29uc3QgbmV3UHJpb3JpdHkgPSBvYmoucm93SW5kZXg7XG5cdFx0XHRpZiAoIWlzTmFOKCBydWxlSWQgKSAmJiBvbGRQcmlvcml0eSAhPT0gbmV3UHJpb3JpdHkpIHtcblx0XHRcdFx0cHJpb3JpdHlXYXNDaGFuZ2VkID0gdHJ1ZTtcblx0XHRcdFx0cHJpb3JpdHlEYXRhW3J1bGVJZF0gPSBuZXdQcmlvcml0eTtcblx0XHRcdH1cblx0XHR9KTtcblx0XHRpZiAocHJpb3JpdHlXYXNDaGFuZ2VkKSB7XG5cdFx0XHQkLmFwaSh7XG5cdFx0XHRcdG9uOiAnbm93Jyxcblx0XHRcdFx0dXJsOiBgJHtnbG9iYWxSb290VXJsfSR7aWRVcmx9L2NoYW5nZVByaW9yaXR5P3RhYmxlPWArJCh0YWJsZSkuYXR0cignaWQnKS5yZXBsYWNlKCctdGFibGUnLCAnJyksXG5cdFx0XHRcdG1ldGhvZDogJ1BPU1QnLFxuXHRcdFx0XHRkYXRhOiBwcmlvcml0eURhdGEsXG5cdFx0XHR9KTtcblx0XHR9XG5cdH0sXG5cblx0LyoqXG5cdCAqINCe0LrQvtC90YfQsNC90LjQtSDRgNC10LTQsNC60YLQuNGA0L7QstCw0L3QuNGPINC/0L7Qu9GPINCy0LLQvtC00LAuXG5cdCAqINCd0LUg0L7RgtC90L7RgdC40YLRgdGPINC6IHNlbGVjdC5cblx0ICogQHBhcmFtIGVcblx0ICovXG5cdGVuZEVkaXRJbnB1dChlKXtcblx0XHRsZXQgJGVsID0gJCgnLmNoYW5nZWQtZmllbGQnKS5jbG9zZXN0KCd0cicpO1xuXHRcdCRlbC5lYWNoKGZ1bmN0aW9uIChpbmRleCwgb2JqKSB7XG5cdFx0XHRsZXQgY3VycmVudFJvd0lkID0gJChvYmopLmF0dHIoJ2lkJyk7XG5cdFx0XHRsZXQgdGFibGVOYW1lICAgID0gJChvYmopLmNsb3Nlc3QoJ3RhYmxlJykuYXR0cignaWQnKS5yZXBsYWNlKCctdGFibGUnLCAnJyk7XG5cdFx0XHRpZiAoY3VycmVudFJvd0lkICE9PSB1bmRlZmluZWQgJiYgdGFibGVOYW1lICE9PSB1bmRlZmluZWQpIHtcblx0XHRcdFx0d2luZG93W2NsYXNzTmFtZV0uc2VuZENoYW5nZXNUb1NlcnZlcih0YWJsZU5hbWUsIGN1cnJlbnRSb3dJZCk7XG5cdFx0XHR9XG5cdFx0fSk7XG5cdH0sXG5cblx0LyoqXG5cdCAqINCU0L7QsdCw0LLQu9C10L3QuNC1INC90L7QstC+0Lkg0YHRgtGA0L7QutC4INCyINGC0LDQsdC70LjRhtGDLlxuXHQgKiBAcGFyYW0gZVxuXHQgKi9cblx0YWRkTmV3Um93KGUpe1xuXHRcdGxldCBpZFRhYmxlID0gJChlLnRhcmdldCkuYXR0cignaWQtdGFibGUnKTtcblx0XHRsZXQgdGFibGUgICA9ICQoJyMnK2lkVGFibGUpO1xuXHRcdGUucHJldmVudERlZmF1bHQoKTtcblx0XHR0YWJsZS5maW5kKCcuZGF0YVRhYmxlc19lbXB0eScpLnJlbW92ZSgpO1xuXHRcdC8vINCe0YLQv9GA0LDQstC40Lwg0L3QsCDQt9Cw0L/QuNGB0Ywg0LLRgdC1INGH0YLQviDQvdC1INC30LDQv9C40YHQsNC90L4g0LXRidC1XG5cdFx0bGV0ICRlbCA9IHRhYmxlLmZpbmQoJy5jaGFuZ2VkLWZpZWxkJykuY2xvc2VzdCgndHInKTtcblx0XHQkZWwuZWFjaChmdW5jdGlvbiAoaW5kZXgsIG9iaikge1xuXHRcdFx0bGV0IGN1cnJlbnRSb3dJZCA9ICQob2JqKS5hdHRyKCdpZCcpO1xuXHRcdFx0aWYgKGN1cnJlbnRSb3dJZCAhPT0gdW5kZWZpbmVkKSB7XG5cdFx0XHRcdHdpbmRvd1tjbGFzc05hbWVdLnNlbmRDaGFuZ2VzVG9TZXJ2ZXIoY3VycmVudFJvd0lkKTtcblx0XHRcdH1cblx0XHR9KTtcblx0XHRsZXQgaWQgPSBcIm5ld1wiK01hdGguZmxvb3IoTWF0aC5yYW5kb20oKSAqIE1hdGguZmxvb3IoNTAwKSk7XG5cdFx0bGV0IHJvd1RlbXBsYXRlID0gJzx0ciBpZD1cIicraWQrJ1wiIHJvbGU9XCJyb3dcIiBjbGFzcz1cImV2ZW5cIj4nK3RhYmxlLmZpbmQoJ3RyI1RFTVBMQVRFJykuaHRtbCgpLnJlcGxhY2UoJ1RFTVBMQVRFJywgaWQpKyc8L3RyPic7XG5cdFx0dGFibGUuZmluZCgndGJvZHkgPiB0cjpmaXJzdCcpLmJlZm9yZShyb3dUZW1wbGF0ZSk7XG5cdFx0d2luZG93W2NsYXNzTmFtZV0uZHJvd1NlbGVjdEdyb3VwKGlkVGFibGUpO1xuXHR9LFxuXHQvKipcblx0ICog0J7QsdC90L7QstC70LXQvdC40LUgc2VsZWN0INGN0LvQtdC80LXQvdGC0L7Qsi5cblx0ICogQHBhcmFtIHRhYmxlSWRcblx0ICovXG5cdGRyb3dTZWxlY3RHcm91cCh0YWJsZUlkKSB7XG5cdFx0JCgnIycgKyB0YWJsZUlkKS5maW5kKCd0ciNURU1QTEFURScpLmhpZGUoKTtcblx0XHRsZXQgc2VsZXN0R3JvdXAgPSAkKCcuc2VsZWN0LWdyb3VwJyk7XG5cdFx0c2VsZXN0R3JvdXAuZWFjaCgoaW5kZXgsIG9iaikgPT4ge1xuXHRcdFx0bGV0IHNlbGVjdFR5cGUgPSAkKG9iaikuY2xvc2VzdCgndGQnKS5maW5kKCdpbnB1dCcpLmF0dHIoJ3NlbGVjdFR5cGUnKTtcblx0XHRcdCQob2JqKS5kcm9wZG93bih7XG5cdFx0XHRcdHZhbHVlczogd2luZG93W2NsYXNzTmFtZV0ubWFrZURyb3Bkb3duTGlzdChzZWxlY3RUeXBlLCAkKG9iaikuYXR0cignZGF0YS12YWx1ZScpKSxcblx0XHRcdH0pO1xuXHRcdH0pO1xuXHRcdHNlbGVzdEdyb3VwLmRyb3Bkb3duKHtcblx0XHRcdG9uQ2hhbmdlOiB3aW5kb3dbY2xhc3NOYW1lXS5jaGFuZ2VHcm91cEluTGlzdCxcblx0XHR9KTtcblxuXHRcdCQoJyMnICsgdGFibGVJZCkudGFibGVEbkQoe1xuXHRcdFx0b25Ecm9wOiB3aW5kb3dbY2xhc3NOYW1lXS5jYk9uRHJvcCxcblx0XHRcdG9uRHJhZ0NsYXNzOiAnaG92ZXJpbmdSb3cnLFxuXHRcdFx0ZHJhZ0hhbmRsZTogJy5kcmFnSGFuZGxlJyxcblx0XHR9KTtcblx0fSxcblxuXHQvKipcblx0ICog0KPQtNCw0LvQtdC90LjQtSDRgdGC0YDQvtC60Lhcblx0ICogQHBhcmFtIHRhYmxlTmFtZVxuXHQgKiBAcGFyYW0gaWQgLSByZWNvcmQgaWRcblx0ICovXG5cdGRlbGV0ZVJvdyh0YWJsZU5hbWUsIGlkKSB7XG5cdFx0bGV0IHRhYmxlID0gJCgnIycrIHRhYmxlTmFtZSsnLXRhYmxlJyk7XG5cdFx0aWYgKGlkLnN1YnN0cigwLDMpID09PSAnbmV3Jykge1xuXHRcdFx0dGFibGUuZmluZCgndHIjJytpZCkucmVtb3ZlKCk7XG5cdFx0XHRyZXR1cm47XG5cdFx0fVxuXHRcdCQuYXBpKHtcblx0XHRcdHVybDogd2luZG93W2NsYXNzTmFtZV0uZGVsZXRlUmVjb3JkQUpBWFVybCsnP2lkPScraWQrJyZ0YWJsZT0nK3RhYmxlTmFtZSxcblx0XHRcdG9uOiAnbm93Jyxcblx0XHRcdG9uU3VjY2VzcyhyZXNwb25zZSkge1xuXHRcdFx0XHRpZiAocmVzcG9uc2Uuc3VjY2Vzcykge1xuXHRcdFx0XHRcdHRhYmxlLmZpbmQoJ3RyIycraWQpLnJlbW92ZSgpO1xuXHRcdFx0XHRcdGlmICh0YWJsZS5maW5kKCd0Ym9keSA+IHRyJykubGVuZ3RoID09PSAwKSB7XG5cdFx0XHRcdFx0XHR0YWJsZS5maW5kKCd0Ym9keScpLmFwcGVuZCgnPHRyIGNsYXNzPVwib2RkXCI+PC90cj4nKTtcblx0XHRcdFx0XHR9XG5cdFx0XHRcdH1cblx0XHRcdH1cblx0XHR9KTtcblx0fSxcblxuXHQvKipcblx0ICog0J7RgtC/0YDQsNCy0LrQsCDQtNCw0L3QvdGL0YUg0L3QsCDRgdC10YDQstC10YAg0L/RgNC4INC40LfQvNC10L3QuNC4XG5cdCAqL1xuXHRzZW5kQ2hhbmdlc1RvU2VydmVyKHRhYmxlTmFtZSwgcmVjb3JkSWQpIHtcblx0XHRsZXQgZGF0YSA9IHsgJ3BieC10YWJsZS1pZCc6IHRhYmxlTmFtZSwgJ3BieC1yb3ctaWQnOiAgcmVjb3JkSWR9O1xuXHRcdGxldCBub3RFbXB0eSA9IGZhbHNlO1xuXHRcdCQoXCJ0ciNcIityZWNvcmRJZCArICcgLicgKyBpbnB1dENsYXNzTmFtZSkuZWFjaChmdW5jdGlvbiAoaW5kZXgsIG9iaikge1xuXHRcdFx0bGV0IGNvbE5hbWUgPSAkKG9iaikuYXR0cignY29sTmFtZScpO1xuXHRcdFx0aWYoY29sTmFtZSAhPT0gdW5kZWZpbmVkKXtcblx0XHRcdFx0ZGF0YVskKG9iaikuYXR0cignY29sTmFtZScpXSA9ICQob2JqKS52YWwoKTtcblx0XHRcdFx0aWYoJChvYmopLnZhbCgpICE9PSAnJyl7XG5cdFx0XHRcdFx0bm90RW1wdHkgPSB0cnVlO1xuXHRcdFx0XHR9XG5cdFx0XHR9XG5cdFx0fSk7XG5cblx0XHRpZihub3RFbXB0eSA9PT0gZmFsc2Upe1xuXHRcdFx0cmV0dXJuO1xuXHRcdH1cblx0XHQkKFwidHIjXCIrcmVjb3JkSWQrXCIgLnVzZXIuY2lyY2xlXCIpLnJlbW92ZUNsYXNzKCd1c2VyIGNpcmNsZScpLmFkZENsYXNzKCdzcGlubmVyIGxvYWRpbmcnKTtcblx0XHQkLmFwaSh7XG5cdFx0XHR1cmw6IHdpbmRvd1tjbGFzc05hbWVdLnNhdmVUYWJsZUFKQVhVcmwsXG5cdFx0XHRvbjogJ25vdycsXG5cdFx0XHRtZXRob2Q6ICdQT1NUJyxcblx0XHRcdGRhdGE6IGRhdGEsXG5cdFx0XHRzdWNjZXNzVGVzdChyZXNwb25zZSkge1xuXHRcdFx0XHRyZXR1cm4gcmVzcG9uc2UgIT09IHVuZGVmaW5lZCAmJiBPYmplY3Qua2V5cyhyZXNwb25zZSkubGVuZ3RoID4gMCAmJiByZXNwb25zZS5zdWNjZXNzID09PSB0cnVlO1xuXHRcdFx0fSxcblx0XHRcdG9uU3VjY2VzcyhyZXNwb25zZSkge1xuXHRcdFx0XHRpZiAocmVzcG9uc2UuZGF0YSAhPT0gdW5kZWZpbmVkKSB7XG5cdFx0XHRcdFx0bGV0IHJvd0lkID0gcmVzcG9uc2UuZGF0YVsncGJ4LXJvdy1pZCddO1xuXHRcdFx0XHRcdGxldCB0YWJsZSA9ICQoJyMnK3Jlc3BvbnNlLmRhdGFbJ3BieC10YWJsZS1pZCddKyctdGFibGUnKTtcblx0XHRcdFx0XHR0YWJsZS5maW5kKFwidHIjXCIgKyByb3dJZCArIFwiIGlucHV0XCIpLmF0dHIoJ3JlYWRvbmx5JywgdHJ1ZSk7XG5cdFx0XHRcdFx0dGFibGUuZmluZChcInRyI1wiICsgcm93SWQgKyBcIiBkaXZcIikucmVtb3ZlQ2xhc3MoJ2NoYW5nZWQtZmllbGQgbG9hZGluZycpLmFkZENsYXNzKCd0cmFuc3BhcmVudCcpO1xuXHRcdFx0XHRcdHRhYmxlLmZpbmQoXCJ0ciNcIiArIHJvd0lkICsgXCIgLnNwaW5uZXIubG9hZGluZ1wiKS5hZGRDbGFzcygndXNlciBjaXJjbGUnKS5yZW1vdmVDbGFzcygnc3Bpbm5lciBsb2FkaW5nJyk7XG5cblx0XHRcdFx0XHRpZiAocm93SWQgIT09IHJlc3BvbnNlLmRhdGFbJ25ld0lkJ10pe1xuXHRcdFx0XHRcdFx0JChgdHIjJHtyb3dJZH1gKS5hdHRyKCdpZCcsIHJlc3BvbnNlLmRhdGFbJ25ld0lkJ10pO1xuXHRcdFx0XHRcdH1cblx0XHRcdFx0fVxuXHRcdFx0fSxcblx0XHRcdG9uRmFpbHVyZShyZXNwb25zZSkge1xuXHRcdFx0XHRpZiAocmVzcG9uc2UubWVzc2FnZSAhPT0gdW5kZWZpbmVkKSB7XG5cdFx0XHRcdFx0VXNlck1lc3NhZ2Uuc2hvd011bHRpU3RyaW5nKHJlc3BvbnNlLm1lc3NhZ2UpO1xuXHRcdFx0XHR9XG5cdFx0XHRcdCQoXCJ0ciNcIiArIHJlY29yZElkICsgXCIgLnNwaW5uZXIubG9hZGluZ1wiKS5hZGRDbGFzcygndXNlciBjaXJjbGUnKS5yZW1vdmVDbGFzcygnc3Bpbm5lciBsb2FkaW5nJyk7XG5cdFx0XHR9LFxuXHRcdFx0b25FcnJvcihlcnJvck1lc3NhZ2UsIGVsZW1lbnQsIHhocikge1xuXHRcdFx0XHRpZiAoeGhyLnN0YXR1cyA9PT0gNDAzKSB7XG5cdFx0XHRcdFx0d2luZG93LmxvY2F0aW9uID0gZ2xvYmFsUm9vdFVybCArIFwic2Vzc2lvbi9pbmRleFwiO1xuXHRcdFx0XHR9XG5cdFx0XHR9XG5cdFx0fSk7XG5cdH0sXG5cblx0LyoqXG5cdCAqIENoYW5nZSBzb21lIGZvcm0gZWxlbWVudHMgY2xhc3NlcyBkZXBlbmRzIG9mIG1vZHVsZSBzdGF0dXNcblx0ICovXG5cdGNoZWNrU3RhdHVzVG9nZ2xlKCkge1xuXHRcdGlmICh3aW5kb3dbY2xhc3NOYW1lXS4kc3RhdHVzVG9nZ2xlLmNoZWNrYm94KCdpcyBjaGVja2VkJykpIHtcblx0XHRcdHdpbmRvd1tjbGFzc05hbWVdLiRkaXNhYmlsaXR5RmllbGRzLnJlbW92ZUNsYXNzKCdkaXNhYmxlZCcpO1xuXHRcdFx0d2luZG93W2NsYXNzTmFtZV0uJG1vZHVsZVN0YXR1cy5zaG93KCk7XG5cdFx0fSBlbHNlIHtcblx0XHRcdHdpbmRvd1tjbGFzc05hbWVdLiRkaXNhYmlsaXR5RmllbGRzLmFkZENsYXNzKCdkaXNhYmxlZCcpO1xuXHRcdFx0d2luZG93W2NsYXNzTmFtZV0uJG1vZHVsZVN0YXR1cy5oaWRlKCk7XG5cdFx0fVxuXHR9LFxuXG5cdC8qKlxuXHQgKiBTZW5kIGNvbW1hbmQgdG8gcmVzdGFydCBtb2R1bGUgd29ya2VycyBhZnRlciBkYXRhIGNoYW5nZXMsXG5cdCAqIEFsc28gd2UgY2FuIGRvIGl0IG9uIFRlbXBsYXRlQ29uZi0+bW9kZWxzRXZlbnRDaGFuZ2VEYXRhIG1ldGhvZFxuXHQgKi9cblx0YXBwbHlDb25maWd1cmF0aW9uQ2hhbmdlcygpIHtcblx0XHR3aW5kb3dbY2xhc3NOYW1lXS5jaGFuZ2VTdGF0dXMoJ1VwZGF0aW5nJyk7XG5cdFx0JC5hcGkoe1xuXHRcdFx0dXJsOiBgJHtDb25maWcucGJ4VXJsfS9wYnhjb3JlL2FwaS9tb2R1bGVzL2ArY2xhc3NOYW1lK2AvcmVsb2FkYCxcblx0XHRcdG9uOiAnbm93Jyxcblx0XHRcdHN1Y2Nlc3NUZXN0KHJlc3BvbnNlKSB7XG5cdFx0XHRcdC8vIHRlc3Qgd2hldGhlciBhIEpTT04gcmVzcG9uc2UgaXMgdmFsaWRcblx0XHRcdFx0cmV0dXJuIE9iamVjdC5rZXlzKHJlc3BvbnNlKS5sZW5ndGggPiAwICYmIHJlc3BvbnNlLnJlc3VsdCA9PT0gdHJ1ZTtcblx0XHRcdH0sXG5cdFx0XHRvblN1Y2Nlc3MoKSB7XG5cdFx0XHRcdHdpbmRvd1tjbGFzc05hbWVdLmNoYW5nZVN0YXR1cygnQ29ubmVjdGVkJyk7XG5cdFx0XHR9LFxuXHRcdFx0b25GYWlsdXJlKCkge1xuXHRcdFx0XHR3aW5kb3dbY2xhc3NOYW1lXS5jaGFuZ2VTdGF0dXMoJ0Rpc2Nvbm5lY3RlZCcpO1xuXHRcdFx0fSxcblx0XHR9KTtcblx0fSxcblxuXHQvKipcblx0ICogV2UgY2FuIG1vZGlmeSBzb21lIGRhdGEgYmVmb3JlIGZvcm0gc2VuZFxuXHQgKiBAcGFyYW0gc2V0dGluZ3Ncblx0ICogQHJldHVybnMgeyp9XG5cdCAqL1xuXHRjYkJlZm9yZVNlbmRGb3JtKHNldHRpbmdzKSB7XG5cdFx0Y29uc3QgcmVzdWx0ID0gc2V0dGluZ3M7XG5cdFx0cmVzdWx0LmRhdGEgPSB3aW5kb3dbY2xhc3NOYW1lXS4kZm9ybU9iai5mb3JtKCdnZXQgdmFsdWVzJyk7XG5cdFx0cmV0dXJuIHJlc3VsdDtcblx0fSxcblxuXHQvKipcblx0ICogU29tZSBhY3Rpb25zIGFmdGVyIGZvcm1zIHNlbmRcblx0ICovXG5cdGNiQWZ0ZXJTZW5kRm9ybSgpIHtcblx0XHR3aW5kb3dbY2xhc3NOYW1lXS5hcHBseUNvbmZpZ3VyYXRpb25DaGFuZ2VzKCk7XG5cdH0sXG5cblx0LyoqXG5cdCAqIEluaXRpYWxpemUgZm9ybSBwYXJhbWV0ZXJzXG5cdCAqL1xuXHRpbml0aWFsaXplRm9ybSgpIHtcblx0XHRGb3JtLiRmb3JtT2JqID0gd2luZG93W2NsYXNzTmFtZV0uJGZvcm1PYmo7XG5cdFx0Rm9ybS51cmwgPSBgJHtnbG9iYWxSb290VXJsfSR7aWRVcmx9L3NhdmVgO1xuXHRcdEZvcm0udmFsaWRhdGVSdWxlcyA9IHdpbmRvd1tjbGFzc05hbWVdLnZhbGlkYXRlUnVsZXM7XG5cdFx0Rm9ybS5jYkJlZm9yZVNlbmRGb3JtID0gd2luZG93W2NsYXNzTmFtZV0uY2JCZWZvcmVTZW5kRm9ybTtcblx0XHRGb3JtLmNiQWZ0ZXJTZW5kRm9ybSA9IHdpbmRvd1tjbGFzc05hbWVdLmNiQWZ0ZXJTZW5kRm9ybTtcblx0XHRGb3JtLmluaXRpYWxpemUoKTtcblx0fSxcblxuXHQvKipcblx0ICogVXBkYXRlIHRoZSBtb2R1bGUgc3RhdGUgb24gZm9ybSBsYWJlbFxuXHQgKiBAcGFyYW0gc3RhdHVzXG5cdCAqL1xuXHRjaGFuZ2VTdGF0dXMoc3RhdHVzKSB7XG5cdFx0c3dpdGNoIChzdGF0dXMpIHtcblx0XHRcdGNhc2UgJ0Nvbm5lY3RlZCc6XG5cdFx0XHRcdHdpbmRvd1tjbGFzc05hbWVdLiRtb2R1bGVTdGF0dXNcblx0XHRcdFx0XHQucmVtb3ZlQ2xhc3MoJ2dyZXknKVxuXHRcdFx0XHRcdC5yZW1vdmVDbGFzcygncmVkJylcblx0XHRcdFx0XHQuYWRkQ2xhc3MoJ2dyZWVuJyk7XG5cdFx0XHRcdHdpbmRvd1tjbGFzc05hbWVdLiRtb2R1bGVTdGF0dXMuaHRtbChnbG9iYWxUcmFuc2xhdGUubW9kdWxlX2V4cG9ydF9yZWNvcmRzQ29ubmVjdGVkKTtcblx0XHRcdFx0YnJlYWs7XG5cdFx0XHRjYXNlICdEaXNjb25uZWN0ZWQnOlxuXHRcdFx0XHR3aW5kb3dbY2xhc3NOYW1lXS4kbW9kdWxlU3RhdHVzXG5cdFx0XHRcdFx0LnJlbW92ZUNsYXNzKCdncmVlbicpXG5cdFx0XHRcdFx0LnJlbW92ZUNsYXNzKCdyZWQnKVxuXHRcdFx0XHRcdC5hZGRDbGFzcygnZ3JleScpO1xuXHRcdFx0XHR3aW5kb3dbY2xhc3NOYW1lXS4kbW9kdWxlU3RhdHVzLmh0bWwoZ2xvYmFsVHJhbnNsYXRlLm1vZHVsZV9leHBvcnRfcmVjb3Jkc0Rpc2Nvbm5lY3RlZCk7XG5cdFx0XHRcdGJyZWFrO1xuXHRcdFx0Y2FzZSAnVXBkYXRpbmcnOlxuXHRcdFx0XHR3aW5kb3dbY2xhc3NOYW1lXS4kbW9kdWxlU3RhdHVzXG5cdFx0XHRcdFx0LnJlbW92ZUNsYXNzKCdncmVlbicpXG5cdFx0XHRcdFx0LnJlbW92ZUNsYXNzKCdyZWQnKVxuXHRcdFx0XHRcdC5hZGRDbGFzcygnZ3JleScpO1xuXHRcdFx0XHR3aW5kb3dbY2xhc3NOYW1lXS4kbW9kdWxlU3RhdHVzLmh0bWwoYDxpIGNsYXNzPVwic3Bpbm5lciBsb2FkaW5nIGljb25cIj48L2k+JHtnbG9iYWxUcmFuc2xhdGUubW9kdWxlX2V4cG9ydF9yZWNvcmRzVXBkYXRlU3RhdHVzfWApO1xuXHRcdFx0XHRicmVhaztcblx0XHRcdGRlZmF1bHQ6XG5cdFx0XHRcdHdpbmRvd1tjbGFzc05hbWVdLiRtb2R1bGVTdGF0dXNcblx0XHRcdFx0XHQucmVtb3ZlQ2xhc3MoJ2dyZWVuJylcblx0XHRcdFx0XHQucmVtb3ZlQ2xhc3MoJ3JlZCcpXG5cdFx0XHRcdFx0LmFkZENsYXNzKCdncmV5Jyk7XG5cdFx0XHRcdHdpbmRvd1tjbGFzc05hbWVdLiRtb2R1bGVTdGF0dXMuaHRtbChnbG9iYWxUcmFuc2xhdGUubW9kdWxlX2V4cG9ydF9yZWNvcmRzRGlzY29ubmVjdGVkKTtcblx0XHRcdFx0YnJlYWs7XG5cdFx0fVxuXHR9LFxufTtcblxuJChkb2N1bWVudCkucmVhZHkoKCkgPT4ge1xuXHR3aW5kb3dbY2xhc3NOYW1lXS5pbml0aWFsaXplKCk7XG59KTtcblxuIl19