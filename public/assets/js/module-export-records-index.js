"use strict";

function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }
function _slicedToArray(r, e) { return _arrayWithHoles(r) || _iterableToArrayLimit(r, e) || _unsupportedIterableToArray(r, e) || _nonIterableRest(); }
function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _iterableToArrayLimit(r, l) { var t = null == r ? null : "undefined" != typeof Symbol && r[Symbol.iterator] || r["@@iterator"]; if (null != t) { var e, n, i, u, a = [], f = !0, o = !1; try { if (i = (t = t.call(r)).next, 0 === l) { if (Object(t) !== t) return; f = !1; } else for (; !(f = (e = i.call(t)).done) && (a.push(e.value), a.length !== l); f = !0); } catch (r) { o = !0, n = r; } finally { try { if (!f && null != t["return"] && (u = t["return"](), Object(u) !== u)) return; } finally { if (o) throw n; } } return a; } }
function _arrayWithHoles(r) { if (Array.isArray(r)) return r; }
function _toConsumableArray(r) { return _arrayWithoutHoles(r) || _iterableToArray(r) || _unsupportedIterableToArray(r) || _nonIterableSpread(); }
function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(r, a) { if (r) { if ("string" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }
function _iterableToArray(r) { if ("undefined" != typeof Symbol && null != r[Symbol.iterator] || null != r["@@iterator"]) return Array.from(r); }
function _arrayWithoutHoles(r) { if (Array.isArray(r)) return _arrayLikeToArray(r); }
function _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }
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
  $cdrTable: $('#CallDetails-table'),
  $outgoingEmployeeCalls: $('#OutgoingEmployeeCalls-table'),
  $cdrQueueTable: $('#CdrQueue-table'),
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
   * Flag to prevent initial data loading
   * @type {boolean}
   */
  tablesInitialized: false,
  tableInitDataOutgoingEmployeeCalls: {
    wasInit: false,
    id: 'OutgoingEmployeeCalls',
    data: {
      search: {
        search: ''
      },
      serverSide: true,
      processing: true,
      info: false,
      columnDefs: [{
        defaultContent: "",
        targets: "_all"
      }],
      ajax: function ajax(data, callback, settings) {
        if (data.search.value === '' || !ModuleExtendedCDRs.tablesInitialized || $('#currentReportNameID').val() !== 'OutgoingEmployeeCalls') {
          // Skip initial load
          callback({
            data: [],
            recordsTotal: 0,
            recordsFiltered: 0
          });
          return;
        }
        $.ajax({
          url: "".concat(globalRootUrl).concat(idUrl, "/getOutgoingEmployeeCalls"),
          type: 'POST',
          data: data,
          success: function success(json) {
            callback(json);
          }
        });
      },
      paging: false,
      sDom: 'rtip',
      deferRender: true,
      // pageLength: ModuleExtendedCDRs.calculatePageLength(),
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
        ModuleExtendedCDRs.$globalSearch.closest('div').removeClass('loading');
      },
      language: SemanticLocalization.dataTableLocalisation,
      ordering: false
    }
  },
  tableInitDataCallDetails: {
    wasInit: false,
    id: 'CallDetails',
    data: {
      search: {
        search: ''
      },
      serverSide: true,
      processing: true,
      info: false,
      columnDefs: [{
        defaultContent: "-",
        targets: "_all"
      }],
      ajax: function ajax(data, callback, settings) {
        if (data.search.value === '' || !ModuleExtendedCDRs.tablesInitialized || $('#currentReportNameID').val() !== ModuleExtendedCDRs.tableInitDataCallDetails.id) {
          // Skip initial load
          callback({
            data: [],
            recordsTotal: 0,
            recordsFiltered: 0
          });
          return;
        }
        $.ajax({
          url: "".concat(globalRootUrl).concat(idUrl, "/getHistory"),
          type: 'POST',
          data: data,
          success: function success(json) {
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
            callback(json);
          }
        });
      },
      paging: true,
      sDom: 'rtip',
      deferRender: true,
      stripeClasses: ['striped'],
      // pageLength: ModuleExtendedCDRs.calculatePageLength(),
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
        ModuleExtendedCDRs.$globalSearch.closest('div').removeClass('loading');
      },
      language: SemanticLocalization.dataTableLocalisation,
      ordering: false
    }
  },
  tableInitDataCdrQueue: {
    wasInit: false,
    id: 'CdrQueue',
    data: {
      search: {
        search: ''
      },
      serverSide: true,
      processing: true,
      info: false,
      columnDefs: [{
        defaultContent: "",
        targets: "_all"
      }],
      ajax: function ajax(data, callback, settings) {
        if (data.search.value === '' || !ModuleExtendedCDRs.tablesInitialized || $('#currentReportNameID').val() !== 'CdrQueue') {
          // Skip initial load
          callback({
            data: [],
            recordsTotal: 0,
            recordsFiltered: 0
          });
          return;
        }
        $.ajax({
          url: "".concat(globalRootUrl).concat(idUrl, "/getCdrQueue"),
          type: 'POST',
          data: data,
          success: function success(json) {
            callback(json);
          }
        });
      },
      paging: false,
      sDom: 'rtip',
      deferRender: true,
      // pageLength: ModuleExtendedCDRs.calculatePageLength(),
      /**
       * Constructs the CDR Queue row.
       * @param {HTMLElement} row - The row element.
       * @param {Array} data - The row data.
       */
      createdRow: function createdRow(row, data) {
        $(row).attr('data-queue', data.queueId);
        $(row).attr('data-date', data.date);

        // Format date for display and URL
        var dateForUrl = '';
        if (data.date) {
          var dateParts = data.date.split('-');
          if (dateParts.length === 3) {
            dateForUrl = "".concat(dateParts[2], "/").concat(dateParts[1], "/").concat(dateParts[0]);
          }
        }

        // Create detail button
        var dateHtml = data.date;
        if (dateForUrl && data.queueId) {
          var detailUrl = "".concat(globalRootUrl, "module-extended-c-d-rs/index/?reportNameID=CallDetails&dateRangeSelector=").concat(encodeURIComponent(dateForUrl + ' - ' + dateForUrl), "&queue=").concat(encodeURIComponent(data.queueId));
          dateHtml = "".concat(data.date, " <i class=\"external alternate icon link\" style=\"cursor: pointer; margin-left: 5px;\" onclick=\"window.open('").concat(detailUrl, "', '_blank')\" title=\"Open details\"></i>");
        }
        $('td', row).eq(0).html(dateHtml);
        $('td', row).eq(1).html(data.queueName || '-').addClass('center aligned');
        $('td', row).eq(2).html(data.totalCalls).addClass('center aligned');
        $('td', row).eq(3).html(data.answered).addClass('center aligned');
        $('td', row).eq(4).html(data.missed).addClass('center aligned');
        $('td', row).eq(5).html(data.answeredQueue).addClass('center aligned');
        $('td', row).eq(6).html(data.avgWaitTime).addClass('center aligned');
        $('td', row).eq(7).html(data.avgMissed + '%').addClass('center aligned');
        $('td', row).eq(8).html(data.avgWaitTimeQueue).addClass('center aligned');
      },
      drawCallback: function drawCallback(settings) {
        ModuleExtendedCDRs.$globalSearch.closest('div').removeClass('loading');
      },
      language: SemanticLocalization.dataTableLocalisation,
      ordering: false
    }
  },
  /**
   * Field validation rules
   * https://semantic-ui.com/behaviors/form.html
   */
  validateRules: {},
  /**
   * Field validation rules
   * https://semantic-ui.com/behaviors/form.html
   */
  validateVariantRules: {
    title: {
      identifier: 'title',
      rules: [{
        type: 'empty',
        prompt: globalTranslate.repModuleExtendedCDRs_Form_titleReportError
      }]
    },
    minBillSec: {
      identifier: 'minBillSec',
      rules: [{
        type: 'integer[0..1000]',
        prompt: globalTranslate.repModuleExtendedCDRs_Form_titleReportError
      }]
    },
    dateMonth: {
      identifier: 'dateMonth',
      rules: [{
        type: 'integer[1..31]',
        prompt: globalTranslate.repModuleExtendedCDRs_Form_DateMonthError
      }]
    },
    day: {
      identifier: 'day',
      rules: [{
        type: 'regExp[/^((([1-7])(,[1-7])*)|(([1-7])-([1-7])(/([1-7]))?)|(\\*\\/[1-7]))$/]',
        prompt: globalTranslate.repModuleExtendedCDRs_Form_DayError
      }]
    },
    time: {
      identifier: 'time',
      rules: [{
        type: 'regExp[/^(0[0-9]|1[0-9]|2[0-3]):([0-5][0-9])$/]',
        prompt: globalTranslate.repModuleExtendedCDRs_Form_TimeError
      }]
    },
    email: {
      identifier: 'email',
      rules: [{
        type: 'regExp[/^\\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,})(\\s+[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,})*\\s*$/]',
        prompt: globalTranslate.repModuleExtendedCDRs_Form_EmailError
      }]
    }
  },
  /**
   * Process URL parameters for filtering
   */
  processUrlParameters: function processUrlParameters() {
    var urlParams = new URLSearchParams(window.location.search);
    var reportNameID = urlParams.get('reportNameID');
    var dateRangeSelector = urlParams.get('dateRangeSelector');
    var queue = urlParams.get('queue');

    // Set report type if specified
    if (reportNameID) {
      $('#currentReportNameID').val(reportNameID);
    }

    // Set date range if specified
    if (dateRangeSelector) {
      var dates = dateRangeSelector.split(' - ');
      if (dates.length === 2) {
        var startDate = moment(dates[0], 'DD/MM/YYYY');
        var endDate = moment(dates[1], 'DD/MM/YYYY');
        if (startDate.isValid() && endDate.isValid()) {
          ModuleExtendedCDRs.$dateRangeSelector.attr('data-start', startDate.format('YYYY/MM/DD'));
          ModuleExtendedCDRs.$dateRangeSelector.attr('data-end', endDate.endOf('day').format('YYYY/MM/DD'));
          ModuleExtendedCDRs.$dateRangeSelector.val("".concat(startDate.format('DD/MM/YYYY'), " - ").concat(endDate.format('DD/MM/YYYY')));
        }
      }
    }

    // Set queue filter if specified
    if (queue) {
      // Temporarily disable onChange to avoid multiple applyFilter calls
      $('#additionalFilter').dropdown('setting', 'onChange', function () {});

      // Add queue to filter
      var currentFilter = $('#additionalFilter').dropdown('get value');
      var filterArray = currentFilter ? currentFilter.split(',') : [];
      var queueValue = 'queue_' + queue;
      if (!filterArray.includes(queueValue)) {
        filterArray.push(queueValue);
      }
      $('#additionalFilter').dropdown('set selected', filterArray);

      // Re-enable onChange
      $('#additionalFilter').dropdown('setting', 'onChange', ModuleExtendedCDRs.applyFilter);
    }
  },
  /**
   * On page load we init some Semantic UI library
   */
  initialize: function initialize() {
    if (window.location.search) {
      var newUrl = window.location.pathname;
      window.history.replaceState(null, '', newUrl);
    }
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
    $('#content-frame').css('display', 'none');
    // Окончание форматирования базовой страницы
    //////
    ModuleExtendedCDRs.changeReportVariant();
    ModuleExtendedCDRs.initializeDateRangeSelector();

    // Process URL parameters
    ModuleExtendedCDRs.processUrlParameters();

    // инициализируем чекбоксы и выподающие менюшки
    window[className].$checkBoxes.checkbox();
    window[className].initializeForm();
    $('.menu .item').tab();
    $(document).on('click', '#menu-reports i.edit', function (e) {
      e.stopPropagation();
      var parent = $(this).parent();
      $(e.target).parents('div.six.wide.column').children('h4').hide();
      $(e.target).parents('div.six.wide.column').children('div').hide();
      var reportId = $(this).parent().attr('data-report-id');
      var form = $("form[data-report-id=\"".concat(reportId, "\"]"));
      form.show();
      form.form('set value', 'reportNameID', reportId);
      form.form('set value', 'variantId', parent.attr('data-variant-id'));
      form.form('set value', 'title', parent.find('div.content div.title').text().trim());
      form.form('set value', 'minBillSec', parent.attr('data-min-bill-sec'));
      form.form('set value', 'sendingScheduledReport', parent.attr('data-sending-scheduled-report') === '1');
      form.form('set value', 'dateMonth', parent.attr('data-date-month'));
      form.form('set value', 'day', parent.attr('data-day'));
      form.form('set value', 'time', parent.attr('data-time'));
      form.form('set value', 'email', parent.attr('data-email'));
    });
    $(document).on('click', '#menu-reports form button[data-action="save"]', function (e) {
      e.stopPropagation();
      var form = $(this).parent();
      form.form({
        fields: ModuleExtendedCDRs.validateVariantRules
      });
      var reportNameID = form.form('get value', 'reportNameID');
      var variantId = form.form('get value', 'variantId');
      var title = form.form('get value', 'title').trim();
      var minBillSec = form.form('get value', 'minBillSec');
      var sendingReport = form.form('get value', 'sendingScheduledReport') ? 1 : 0;
      var dateMonth = form.form('get value', 'dateMonth');
      var day = form.form('get value', 'day');
      var time = form.form('get value', 'time');
      var email = form.form('get value', 'email');
      if (!form.form('is valid', 'title')) {
        form.form('submit');
        return;
      }
      if (!form.form('is valid', 'minBillSec')) {
        form.form('submit');
        return;
      }
      if (dateMonth && !form.form('is valid', 'dateMonth')) {
        form.form('submit');
        return;
      }
      if (day && !form.form('is valid', 'day')) {
        form.form('submit');
        return;
      }
      if (time && !form.form('is valid', 'time')) {
        form.form('submit');
        return;
      }
      if (email && !form.form('is valid', 'email')) {
        form.form('submit');
        return;
      }
      var parent = $("a[data-variant-id=\"".concat(variantId, "\"][data-report-id=\"").concat(reportNameID, "\"]"));
      parent.find('div.content div.title').text(title);
      parent.attr({
        'data-min-bill-sec': minBillSec,
        'data-sending-scheduled-report': sendingReport,
        'data-date-month': dateMonth,
        'data-day': day,
        'data-time': time,
        'data-email': email
      });
      var contentDiv = parent.find('div.content');
      contentDiv.find('div.input').hide();
      contentDiv.find('div.title').show();
      parent.find('i.star').show();
      form.hide();
      $(e.target).parents('div.six.wide.column').children('h4').show();
      $(e.target).parents('div.six.wide.column').children('div').show();
      ModuleExtendedCDRs.tablesInitialized = false;
      ModuleExtendedCDRs.changeReportVariant(reportNameID, variantId);
      ModuleExtendedCDRs.saveSearchSettings();
      ModuleExtendedCDRs.applyFilter();
    });
    $('#menu-reports i.copy').on('click', function (e) {
      e.stopPropagation();
      var reportId = $(this).parent().attr('id');
      var timestamp = Date.now();
      var parentTitle = $(this).parent().find('div.content').text().trim();
      var html = "<i class=\"small star outline yellow icon\" style=\"display: none;padding-top: 3px\"></i>\n<i class=\"small edit outline icon\" style=\"padding-top: 3px\"></i>\n<i class=\"small trash alternate outline outline red icon\" style=\"padding-top: 3px\"></i>\n<div class=\"content\">\n\t<div class=\"title\">".concat(parentTitle, " (").concat(timestamp, ")</div>\n\t<div class=\"ui mini input fluid hidden\" style=\"display: none;\">\n\t  <input type=\"text\" value=\"").concat(parentTitle, " (").concat(timestamp, ")\">\n\t</div>\n</div>");
      var newItem = $('<a>', {
        "class": 'item',
        html: html,
        'data-report-id': reportId,
        'data-is-main': '0',
        'data-min-bill-sec': $(this).parent().attr('data-min-bill-sec'),
        'data-search-text': $(this).parent().attr('data-search-text'),
        'data-variant-id': timestamp
      });
      $(this).parent().siblings('.ui.link.list').append(newItem);
      $("a[data-variant-id=\"".concat(timestamp, "\"][data-report-id=\"").concat(reportId, "\"] i.edit")).trigger('click');
    });
    $('#menu-reports i.star').on('click', function (e) {
      e.stopPropagation();
      var reportNameID = $(this).parent().attr('id');
      if (reportNameID === undefined) {
        reportNameID = $(this).parent().attr('data-report-id');
      }
      var variantId = $(this).parent().attr('data-variant-id');
      var self = $(this);
      $.ajax({
        url: "".concat(globalRootUrl).concat(idUrl, "/saveMainVariantReport"),
        type: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest'
        },
        data: {
          'reportNameID': reportNameID,
          'variantId': variantId
        },
        success: function success(response) {
          $('#menu-reports i.star').addClass('outline');
          self.removeClass('outline');
        },
        error: function error(xhr, status, _error) {
          console.error(_error);
        }
      });
    });
    $(document).on('click', '#menu-reports i.trash', function (e) {
      e.stopPropagation();
      var reportNameID = $(this).parent().attr('data-report-id');
      var variantId = $(this).parent().attr('data-variant-id');
      var self = $(this);
      $.ajax({
        url: "".concat(globalRootUrl).concat(idUrl, "/removeVariantReport"),
        type: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest'
        },
        data: {
          'reportNameID': reportNameID,
          'variantId': variantId
        },
        success: function success(response) {
          self.parent().remove();
        },
        error: function error(xhr, status, _error2) {
          console.error(_error2);
        }
      });
    });
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
      ModuleExtendedCDRs.tablesInitialized = false;
      ModuleExtendedCDRs.changeReportVariant(reportId);
      ModuleExtendedCDRs.applyFilter();
    });
    $(document).on('click', 'div.column div.link.list a.item div.title', function (e) {
      var reportId = $(e.target).closest('a').attr('data-report-id');
      var variantId = $(e.target).closest('a').attr('data-variant-id');
      $("h1.header i.th.icon").popup('hide');
      ModuleExtendedCDRs.tablesInitialized = false;
      ModuleExtendedCDRs.changeReportVariant(reportId, variantId);
      ModuleExtendedCDRs.applyFilter();
    });
    ModuleExtendedCDRs.$globalSearch.on('keyup', function (e) {
      if (e.keyCode === 13 || e.keyCode === 8 || ModuleExtendedCDRs.$globalSearch.val().length === 0) {
        ModuleExtendedCDRs.applyFilter();
      }
    });

    // Handle minBillSec field changes
    $('#currentMinBillSec').on('change', function () {
      var value = $(this).val();
      var reportNameID = $('#currentReportNameID').val();
      var currentVariantId = $('#currentVariantId').val();

      // Update data attribute
      if (currentVariantId === '') {
        $("h4#".concat(reportNameID)).attr('data-min-bill-sec', value);
      } else {
        $("a[data-variant-id=\"".concat(currentVariantId, "\"][data-report-id=\"").concat(reportNameID, "\"]")).attr('data-min-bill-sec', value);
      }
      ModuleExtendedCDRs.applyFilter();
    });

    // Initialize and handle minBillSecComp dropdown changes
    $('#currentMinBillSecComp').dropdown({
      onChange: function onChange(value, text, $selectedItem) {
        var reportNameID = $('#currentReportNameID').val();
        var currentVariantId = $('#currentVariantId').val();

        // Update button text and data attribute
        $('#currentMinBillSecComp').attr('data-value', value);
        if (currentVariantId === '') {
          $("h4#".concat(reportNameID)).attr('data-min-bill-sec-comp', value);
        } else {
          $("a[data-variant-id=\"".concat(currentVariantId, "\"][data-report-id=\"").concat(reportNameID, "\"]")).attr('data-min-bill-sec-comp', value);
        }
        ModuleExtendedCDRs.applyFilter();
      }
    });
    ModuleExtendedCDRs.$formObj.keydown(function (event) {
      if (event.keyCode === 13) {
        event.preventDefault();
        return false;
      }
    });
    ModuleExtendedCDRs.updateSettings();
    ModuleExtendedCDRs.applyFilter();
    window[className].$dropDowns.dropdown({
      onChange: ModuleExtendedCDRs.applyFilter
    });
    window[className].updateSyncState();
    setInterval(window[className].updateSyncState, 5000);
  },
  changeReportVariant: function changeReportVariant() {
    var reportNameID = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : '';
    var currentVariantId = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : '';
    if (reportNameID === '') {
      reportNameID = $('#currentReportNameID').val();
      currentVariantId = $('#currentVariantId').val();
    }
    $('[id$="-table-div"]').hide();
    $("#".concat(reportNameID, "-table-div")).show();
    $('#currentReportNameID').val(reportNameID);
    $('#currentVariantId').val(currentVariantId);
    if (reportNameID === 'CdrQueue') {
      $('#typeCall').closest('.ui.column').hide();
      $('#minBillSecContainer').hide();
    } else {
      $('#typeCall').closest('.ui.column').show();
      $('#minBillSecContainer').show();
    }
    var variantName = '';
    if (currentVariantId !== '') {
      variantName = $("a[data-variant-id=\"".concat(currentVariantId, "\"][data-report-id=\"").concat(reportNameID, "\"] div.title")).text().trim();
    } else {
      variantName = $("h4#".concat(reportNameID, " div.content")).text().trim();
    }
    $("h1.header div.content").contents().filter(function () {
      return this.nodeType === 3 && this.nodeValue.trim() !== '';
    }).each(function () {
      this.nodeValue = variantName;
    });
    ModuleExtendedCDRs.updateSettings();
  },
  updateSettings: function updateSettings() {
    ModuleExtendedCDRs.tablesInitialized = false;
    var currentVariantId = $('#currentVariantId').val();
    var reportNameID = $('#currentReportNameID').val();
    var settings = {};
    if (currentVariantId === '') {
      settings = JSON.parse(decodeURIComponent($("#".concat(reportNameID)).attr('data-search-text')));
    } else {
      settings = JSON.parse(decodeURIComponent($("a[data-variant-id=\"".concat(currentVariantId, "\"][data-report-id=\"").concat(reportNameID, "\"]")).attr('data-search-text')));
    }

    // Update minBillSec field
    var minBillSec = 0;
    var minBillSecComp = '>';
    if (currentVariantId === '') {
      minBillSec = $("h4#".concat(reportNameID)).attr('data-min-bill-sec') || 0;
      minBillSecComp = $("h4#".concat(reportNameID)).attr('data-min-bill-sec-comp') || '>';
    } else {
      minBillSec = $("a[data-variant-id=\"".concat(currentVariantId, "\"][data-report-id=\"").concat(reportNameID, "\"]")).attr('data-min-bill-sec') || 0;
      minBillSecComp = $("a[data-variant-id=\"".concat(currentVariantId, "\"][data-report-id=\"").concat(reportNameID, "\"]")).attr('data-min-bill-sec-comp') || '>';
    }
    $('#currentMinBillSec').val(minBillSec);
    $('#currentMinBillSecComp').dropdown('set selected', minBillSecComp);
    $('#currentMinBillSecComp').attr('data-value', minBillSecComp);
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
    // Temporarily disable onChange to avoid multiple applyFilter calls
    $('#additionalFilter').dropdown('setting', 'onChange', function () {});
    $('#additionalFilter').dropdown('clear');
    if ($('#pAdditionalFilterString').val() !== '-') {
      $('#additionalFilter').dropdown('set selected', $('#pAdditionalFilterString').val().split(' '));
      settings.typeCall = undefined;
      settings.globalSearch = undefined;
    } else if (settings.additionalFilter !== undefined) {
      $('#additionalFilter').dropdown('set selected', settings.additionalFilter.split(' '));
    }
    // Re-enable onChange
    $('#additionalFilter').dropdown('setting', 'onChange', ModuleExtendedCDRs.applyFilter);
    if (settings.globalSearch !== undefined) {
      ModuleExtendedCDRs.$globalSearch.val(settings.globalSearch);
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
      htmlPlayer += "\n\t\t\t<tr id=\"".concat(record.id, "\" data-row-id=\"").concat(id, "-detailed\" class=\"warning detailed odd shown\" role=\"row\">\n\t\t\t\t<td></td>\n\t\t\t\t<td class=\"right aligned\">").concat(record.start, "</td>\n\t\t\t\t<td data-phone=\"").concat(record.src_num, "\" class=\"right aligned need-update\">").concat(record.src_num, "</td>\n\t\t\t   \t<td data-phone=\"").concat(record.dst_num, "\" class=\"left aligned need-update\">").concat(record.dst_num, "</td>\n\t\t\t\t<td class=\"right aligned\">\t\t\t\n\t\t\t\t</td>\n\t\t\t\t<td class=\"right aligned\">").concat(record.waitTime, "</td>\n\t\t\t\t<td class=\"right aligned\" style=\"padding-right: 3px\">\n\t\t\t\t  <div class=\"ui horizontal list\" style=\"width: 100%; display: flex; align-items: center;\">\n\t\t\t\t\t<div class=\"item\">\n\t\t\t\t\t  <i class=\"ui icon play\"></i>\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"item\" style=\"margin-left:0; flex-grow: 1;\">\n\t\t\t\t\t  <div class=\"ui range cdr-player\" data-value=\"").concat(record.id, "\"></div>\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"item\">\n\t\t\t\t\t  ").concat(record.billsec, "\n\t\t\t\t\t  <i class=\"ui icon download\" data-value=\"").concat(srcDownloadAudio, "\" onclick=\"ModuleExtendedCDRs.audioPlayHandler(event)\"></i>\n\t\t\t\t\t</div>\n\t\t\t\t  </div>\n\t\t\t\t  <audio preload=\"metadata\" id=\"audio-player-").concat(record.id, "\" src=\"").concat(srcAudio, "\" onplay=\"ModuleExtendedCDRs.audioPlayHandler(event)\"></audio>\n\t\t\t\t</td>\n\t\t\t\t<td class=\"right aligned\" data-state-index=\"").concat(record.stateCallIndex, "\">").concat(record.stateCall, "</td>\n\t\t\t</tr>");
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
    var rowHeight = ModuleExtendedCDRs.$cdrTable.find('tbody > tr').first().outerHeight();

    // Calculate window height and available space for table
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
        error: function error(xhr, status, _error3) {
          console.error("Ошибка запроса", status, _error3);
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
      error: function error(xhr, status, _error4) {
        console.error("Ошибка запроса", status, _error4);
      }
    });
  },
  /**
   * Initializes the date range selector.
   */
  initializeDateRangeSelector: function initializeDateRangeSelector() {
    var period = ModuleExtendedCDRs.$dateRangeSelector.attr('data-def-value');
    var defPeriod = [moment(), moment()];
    if (period !== '' && period !== undefined) {
      if ($('#pDateRangeSelector').val() === period) {
        var _period$split = period.split(' - '),
          _period$split2 = _slicedToArray(_period$split, 2),
          startStr = _period$split2[0],
          endStr = _period$split2[1];
        defPeriod = [moment(startStr, 'DD/MM/YYYY'), moment(endStr, 'DD/MM/YYYY')];
      } else {
        var periods = ModuleExtendedCDRs.getStandardPeriods();
        if (periods[period] !== undefined) {
          defPeriod = periods[period];
        }
      }
    }
    var options = {};
    options.ranges = _defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty(_defineProperty({}, globalTranslate.repModuleExtendedCDRs_cdr_cal_Today, [moment(), moment()]), globalTranslate.repModuleExtendedCDRs_cdr_cal_Yesterday, [moment().subtract(1, 'days'), moment().subtract(1, 'days')]), globalTranslate.repModuleExtendedCDRs_cdr_cal_LastWeek, [moment().subtract(6, 'days'), moment()]), globalTranslate.repModuleExtendedCDRs_cdr_cal_Last30Days, [moment().subtract(29, 'days'), moment()]), globalTranslate.repModuleExtendedCDRs_cdr_cal_ThisMonth, [moment().startOf('month'), moment().endOf('month')]), globalTranslate.repModuleExtendedCDRs_cdr_cal_LastMonth, [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]);
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

    // Enable data loading for tables
    ModuleExtendedCDRs.tablesInitialized = true;
    var reportName = $('#currentReportNameID').val();
    if (reportName === ModuleExtendedCDRs.tableInitDataCallDetails.id) {
      if (ModuleExtendedCDRs.tableInitDataCallDetails.wasInit === false) {
        ModuleExtendedCDRs.$cdrTable.dataTable(ModuleExtendedCDRs.tableInitDataCallDetails.data);
        ModuleExtendedCDRs.tableInitDataCallDetails.wasInit = true;
        ModuleExtendedCDRs.$cdrTable.on('click', 'tr.negative', function (e) {
          var ids = $(e.target).attr('data-ids');
          if (ids !== undefined && ids !== '') {
            window.location = "".concat(globalRootUrl, "system-diagnostic/index/?filename=asterisk/verbose&filter=").concat(ids);
          }
        });
        // Add event listener for opening and closing details
        ModuleExtendedCDRs.$cdrTable.on('click', 'tr.detailed', function (e) {
          var ids = $(e.target).attr('data-ids');
          if (ids !== undefined && ids !== '') {
            window.location = "".concat(globalRootUrl, "system-diagnostic/index/?filename=asterisk/verbose&filter=").concat(ids);
            return;
          }
          var tr = $(e.target).closest('tr');
          var row = ModuleExtendedCDRs.$cdrTable.DataTable().row(tr);
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
      }
      var table = ModuleExtendedCDRs.$cdrTable.DataTable();
      table.page.len(ModuleExtendedCDRs.calculatePageLength()).draw();
      table.search(text).draw();
    } else if (reportName === ModuleExtendedCDRs.tableInitDataOutgoingEmployeeCalls.id) {
      if (ModuleExtendedCDRs.tableInitDataOutgoingEmployeeCalls.wasInit === false) {
        ModuleExtendedCDRs.$outgoingEmployeeCalls.dataTable(ModuleExtendedCDRs.tableInitDataOutgoingEmployeeCalls.data);
        ModuleExtendedCDRs.tableInitDataOutgoingEmployeeCalls.wasInit = true;
      }
      var _table = ModuleExtendedCDRs.$outgoingEmployeeCalls.DataTable();
      _table.page.len(ModuleExtendedCDRs.calculatePageLength()).draw();
      _table.search(text).draw();
    } else if (reportName === ModuleExtendedCDRs.tableInitDataCdrQueue.id) {
      if (ModuleExtendedCDRs.tableInitDataCdrQueue.wasInit === false) {
        ModuleExtendedCDRs.$cdrQueueTable.dataTable(ModuleExtendedCDRs.tableInitDataCdrQueue.data);
        ModuleExtendedCDRs.tableInitDataCdrQueue.wasInit = true;
      }
      var _table2 = ModuleExtendedCDRs.$cdrQueueTable.DataTable();
      _table2.page.len(ModuleExtendedCDRs.calculatePageLength()).draw();
      _table2.search(text).draw();
    }
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
    var reportNameID = $('#currentReportNameID').val();
    var currentVariantId = $('#currentVariantId').val();
    var minBilSec = $('#currentMinBillSec').val() || 0;
    var minBilSecComp = $('#currentMinBillSecComp').dropdown('get value') || $('#currentMinBillSecComp').attr('data-value') || '>';
    var filter = {
      dateRangeSelector: dateRangeSelector,
      minBilSec: minBilSec,
      minBilSecComp: minBilSecComp,
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
    var currentVariantId = $('#currentVariantId').val();
    var reportId = $('#currentReportNameID').val();
    var variantName = '';
    var minBillSec = $('#currentMinBillSec').val() || 0;
    var minBillSecComp = $('#currentMinBillSecComp').dropdown('get value') || $('#currentMinBillSecComp').attr('data-value') || '>';
    var data = {
      'search[value]': search,
      'reportNameID': reportId,
      'variantId': currentVariantId,
      'variantName': variantName,
      'minBillSec': minBillSec,
      'minBillSecComp': minBillSecComp
    };
    if (currentVariantId !== '') {
      var parent = $("a[data-variant-id=\"".concat(currentVariantId, "\"][data-report-id=\"").concat(reportId, "\"]"));
      data.variantName = parent.find('div.title').text().trim();
      data.sendingScheduledReport = parent.attr('data-sending-scheduled-report').trim();
      data.dateMonth = parent.attr('data-date-month').trim();
      data.day = parent.attr('data-day').trim();
      data.time = parent.attr('data-time').trim();
      data.email = parent.attr('data-email').trim();
    }
    $.ajax({
      url: "".concat(globalRootUrl).concat(idUrl, "/saveSearchSettings"),
      type: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      data: data,
      success: function success(response) {
        if (currentVariantId === '') {
          $("#".concat($('#currentReportNameID').val())).attr('data-search-text', encodeURIComponent(search));
          $("#".concat($('#currentReportNameID').val())).attr('data-min-bill-sec', minBillSec);
          $("#".concat($('#currentReportNameID').val())).attr('data-min-bill-sec-comp', minBillSecComp);
        } else {
          $("a[data-variant-id=\"".concat(currentVariantId, "\"][data-report-id=\"").concat(reportId, "\"]")).attr('data-search-text', encodeURIComponent(search));
          $("a[data-variant-id=\"".concat(currentVariantId, "\"][data-report-id=\"").concat(reportId, "\"]")).attr('data-min-bill-sec', minBillSec);
          $("a[data-variant-id=\"".concat(currentVariantId, "\"][data-report-id=\"").concat(reportId, "\"]")).attr('data-min-bill-sec-comp', minBillSecComp);
        }
      },
      error: function error(xhr, status, _error5) {
        console.error(_error5);
      }
    });
  },
  startCreateExcelPDF: function startCreateExcelPDF(type) {
    var reportNameID = $('#currentReportNameID').val();
    var currentVariantId = $('#currentVariantId').val();
    var title = '';
    if (currentVariantId === '') {
      title = $("#".concat($('#currentReportNameID').val(), " div.content")).text().trim();
    } else {
      title = $("a[data-variant-id=\"".concat(currentVariantId, "\"][data-report-id=\"").concat(reportNameID, "\"] div.title")).text().trim();
    }
    var encodedSearch = encodeURIComponent(ModuleExtendedCDRs.getSearchText());
    var url = "".concat(window.location.origin, "/pbxcore/api/modules/").concat(className, "/exportHistory?reportNameID=").concat(reportNameID, "&type=").concat(type, "&search=").concat(encodedSearch, "&title=") + encodeURIComponent(title);
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