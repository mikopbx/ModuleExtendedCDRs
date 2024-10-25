/*
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 11 2018
 *
 */
const idUrl     = 'module-extended-c-d-rs';
const idForm    = 'module-extended-cdr-form';
const className = 'ModuleExtendedCDRs';
const inputClassName = 'mikopbx-module-input';
let listenedIDs = [];

/* global globalRootUrl, globalTranslate, Form, Config, $ */
const ModuleExtendedCDRs = {
	$formObj: $('#'+idForm),
	$checkBoxes: $('#'+idForm+' .ui.checkbox'),
	$dropDowns: $('#'+idForm+' .ui.dropdown'),
	saveTableAJAXUrl: globalRootUrl + idUrl + "/saveTableData",
	deleteRecordAJAXUrl: globalRootUrl + idUrl + "/delete",
	$disabilityFields: $('#'+idForm+'  .disability'),
	$statusToggle: $('#module-status-toggle'),
	$moduleStatus: $('#status'),

	/**
	 * The call detail records table element.
	 * @type {jQuery}
	 */
	$cdrTable: $('#CallDetails-table'),
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
	validateRules: {
	},
	/**
	 * Field validation rules
	 * https://semantic-ui.com/behaviors/form.html
	 */
	validateVariantRules: {
		title: {
			identifier: 'title',
			rules: [
				{
					type   : 'empty',
					prompt : globalTranslate.repModuleExtendedCDRs_Form_titleReportError
				}
			]
		},
		minBillSec: {
			identifier: 'minBillSec',
			rules: [
				{
					type   : 'integer[0..1000]',
					prompt : globalTranslate.repModuleExtendedCDRs_Form_titleReportError
				}
			]
		},
		dateMonth: {
			identifier: 'dateMonth',
			rules: [
				{
					type   : 'integer[1..31]',
					prompt : globalTranslate.repModuleExtendedCDRs_Form_DateMonthError
				}
			]
		},
		day: {
			identifier: 'day',
			rules: [
				{
					type   : 'regExp[/^((([1-7])(,[1-7])*)|(([1-7])-([1-7])(/([1-7]))?)|(\\*\\/[1-7]))$/]',
					prompt : globalTranslate.repModuleExtendedCDRs_Form_DayError
				}
			]
		},
		time: {
			identifier: 'time',
			rules: [
				{
					type   : 'regExp[/^(0[0-9]|1[0-9]|2[0-3]):([0-5][0-9])$/]',
					prompt : globalTranslate.repModuleExtendedCDRs_Form_TimeError
				}
			]
		},
		email: {
			identifier: 'email',
			rules: [
				{
					type   : 'regExp[/^\\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,})(\\s+[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,})*\\s*$/]',
					prompt : globalTranslate.repModuleExtendedCDRs_Form_EmailError
				}
			]
		}
	},
	/**
	 * On page load we init some Semantic UI library
	 */
	initialize() {
		//////
		// Удаляем отступы контейнера.
		$('#main-content-container').removeClass('container');
		$('#module-status-toggle-segment').hide();
		$('.ui.clearing.hidden.divider').remove();
		$("h1.header i.puzzle.icon")
			.removeClass('puzzle')
			.addClass('th')
			.css('cursor', 'pointer')
			.popup({
				inline     : true,
				popup: $('#menu-reports'),
				target   : "h1.ui.header",
				position: 'bottom left',
				hoverable: true,
				delay: {
					show: 300,
					hide: 800
				}
			}
		);
		$('#content-frame').css('display', 'none');
		// Окончание форматирования базовой страницы
		//////
		ModuleExtendedCDRs.changeReportVariant();
		ModuleExtendedCDRs.initializeDateRangeSelector();

		// инициализируем чекбоксы и выподающие менюшки
		window[className].$checkBoxes.checkbox();
		window[className].$dropDowns.dropdown({onChange: ModuleExtendedCDRs.applyFilter});
		window.addEventListener('ModuleStatusChanged', window[className].checkStatusToggle);

		window[className].initializeForm();
		$('.menu .item').tab();

		$(document).on('click', '#menu-reports i.edit', function(e) {
			e.stopPropagation();
			let parent =$(this).parent();
			$(e.target).parents('div.six.wide.column').children('h4').hide();
			$(e.target).parents('div.six.wide.column').children('div').hide();

			let reportId = $(this).parent().attr('data-report-id');
			let form = $(`form[data-report-id="${reportId}"]`);
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
		$(document).on('click', '#menu-reports form button[data-action="save"]', function(e) {
			e.stopPropagation();
			let form = $(this).parent();
			form.form({fields: ModuleExtendedCDRs.validateVariantRules});

			let reportNameID  = form.form('get value', 'reportNameID');
			let variantId     = form.form('get value', 'variantId');
			let title         = form.form('get value', 'title').trim();
			let minBillSec    = form.form('get value', 'minBillSec');
			let sendingReport = form.form('get value', 'sendingScheduledReport') ? 1 : 0;
			let dateMonth     = form.form('get value', 'dateMonth');
			let day           = form.form('get value', 'day');
			let time          = form.form('get value', 'time');
			let email         = form.form('get value', 'email');

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

			let parent = $(`a[data-variant-id="${variantId}"][data-report-id="${reportNameID}"]`);
			parent.find('div.content div.title').text(title);
			parent.attr({
				'data-min-bill-sec': minBillSec,
				'data-sending-scheduled-report': sendingReport,
				'data-date-month': dateMonth,
				'data-day': day,
				'data-time': time,
				'data-email': email
			});

			let contentDiv = parent.find('div.content');
			contentDiv.find('div.input').hide();
			contentDiv.find('div.title').show();
			parent.find('i.star').show();
			form.hide();

			$(e.target).parents('div.six.wide.column').children('h4').show();
			$(e.target).parents('div.six.wide.column').children('div').show();

			ModuleExtendedCDRs.changeReportVariant(reportNameID, variantId);
			ModuleExtendedCDRs.saveSearchSettings();
			ModuleExtendedCDRs.applyFilter();
		});
		$('#menu-reports i.copy').on('click', function (e) {
			e.stopPropagation();
			let reportId = $(this).parent().attr('id');
			let timestamp = Date.now();
			let parentTitle = $(this).parent().find('div.content').text().trim();
			let html = `<i class="small star outline yellow icon" style="display: none;padding-top: 3px"></i>
<i class="small edit outline icon" style="padding-top: 3px"></i>
<i class="small trash alternate outline outline red icon" style="padding-top: 3px"></i>
<div class="content">
	<div class="title">${parentTitle} (${timestamp})</div>
	<div class="ui mini input fluid hidden" style="display: none;">
	  <input type="text" value="${parentTitle} (${timestamp})">
	</div>
</div>`;
			let newItem = $('<a>', {
				class: 'item',
				html: html,
				'data-report-id': reportId,
				'data-is-main': '0',
				'data-min-bill-sec': $(this).parent().attr('data-min-bill-sec'),
				'data-search-text': $(this).parent().attr('data-search-text'),
				'data-variant-id': timestamp
			});
			$(this).parent().siblings('.ui.link.list').append(newItem);
			$(`a[data-variant-id="${timestamp}"][data-report-id="${reportId}"] i.edit`).trigger('click');
		});
		$('#menu-reports i.star').on('click', function (e) {
			e.stopPropagation();
			let reportNameID = $(this).parent().attr('id');
			if(reportNameID === undefined){
				reportNameID = $(this).parent().attr('data-report-id');
			}
			let variantId = $(this).parent().attr('data-variant-id');

			let self = $(this);
			$.ajax({
				url: `${globalRootUrl}${idUrl}/saveMainVariantReport`,
				type: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With': 'XMLHttpRequest',
				},
				data: {
					'reportNameID': reportNameID,
					'variantId': 	variantId
				},
				success: function(response) {
					$('#menu-reports i.star').addClass('outline');
					self.removeClass('outline');
				},
				error: function(xhr, status, error) {
					console.error(error);
				}
			});

		});

		$(document).on('click', '#menu-reports i.trash', function(e) {
			e.stopPropagation();
			let reportNameID = $(this).parent().attr('data-report-id');
			let variantId 	 = $(this).parent().attr('data-variant-id');
			let self = $(this);
			$.ajax({
				url: `${globalRootUrl}${idUrl}/removeVariantReport`,
				type: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With': 'XMLHttpRequest',
				},
				data: {
					'reportNameID': reportNameID,
					'variantId': 	variantId
				},
				success: function(response) {
					self.parent().remove();
				},
				error: function(xhr, status, error) {
					console.error(error);
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
			const encodedSearch = encodeURIComponent(ModuleExtendedCDRs.getSearchText());
			const url = `${window.location.origin}/pbxcore/api/modules/${className}/downloads?search=${encodedSearch}`;
			window.open(url, '_blank');
		});
		$('#saveSearchSettings').on('click', function (e) {
			ModuleExtendedCDRs.saveSearchSettings();
		});
		$("div.column h4").on('click', function (e) {
			let reportId = $(this).attr('id');
			$("h1.header i.th.icon").popup('hide');
			ModuleExtendedCDRs.changeReportVariant(reportId);
			ModuleExtendedCDRs.applyFilter();
		});
		$(document).on('click', 'div.column div.link.list a.item div.title', function(e) {
			let reportId = $(e.target).closest('a').attr('data-report-id');
			let variantId = $(e.target).closest('a').attr('data-variant-id');
			$("h1.header i.th.icon").popup('hide');
			ModuleExtendedCDRs.changeReportVariant(reportId, variantId);
			ModuleExtendedCDRs.applyFilter();
		});

		ModuleExtendedCDRs.$globalSearch.on('keyup', (e) => {
			if (e.keyCode === 13
				|| e.keyCode === 8
				|| ModuleExtendedCDRs.$globalSearch.val().length === 0) {
				ModuleExtendedCDRs.applyFilter();
			}
		});

		ModuleExtendedCDRs.$formObj.keydown(function(event){
			if(event.keyCode === 13) {
				event.preventDefault();
				return false;
			}
		});
		ModuleExtendedCDRs.$outgoingEmployeeCalls.dataTable({
			search: {
				search: ModuleExtendedCDRs.getSearchText(),
			},
			serverSide: true,
			processing: true,
			info: false,
			columnDefs: [
				{ defaultContent: "",  targets: "_all"},
			],
			ajax: {
				url: `${globalRootUrl}${idUrl}/getOutgoingEmployeeCalls`,
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
			createdRow(row, data) {
				$('td', row).eq(0).html(data.callerId);
				$('td', row).eq(1).html(data.number).addClass('active');
				$('td', row).eq(2).html(data.billHourCalls);
				$('td', row).eq(3).html(data.billMinCalls);
				$('td', row).eq(4).html(data.billSecCalls);
				$('td', row).eq(5).html(data.countCalls);
			},
			drawCallback(settings) {
				let pagination = $(this).closest('.dataTables_wrapper').find('.dataTables_paginate');
				if (settings._iDisplayLength >= settings.fnRecordsDisplay()) {
					pagination.hide();
				} else {
					pagination.show();
				}
			},
			language: SemanticLocalization.dataTableLocalisation,
			ordering: false,
		});

		ModuleExtendedCDRs.$cdrTable.dataTable({
			search: {
				search: ModuleExtendedCDRs.getSearchText(),
			},
			serverSide: true,
			processing: true,
			info: false,
			columnDefs: [
				{defaultContent: "-", targets: "_all"},
			],
			ajax: {
				url: `${globalRootUrl}${idUrl}/getHistory`,
				type: 'POST',
				dataSrc: function(json) {
					$('a.item[data-tab="all-calls"] b').html(': '+json.recordsFiltered)
					$('a.item[data-tab="incoming-calls"] b').html(': '+json.recordsIncoming)
					$('a.item[data-tab="missed-calls"] b').html(': '+json.recordsMissed)
					$('a.item[data-tab="outgoing-calls"] b').html(': '+json.recordsOutgoing)

					let typeCall = $('#typeCall a.item.active').attr('data-tab');
					if(typeCall === 'incoming-calls'){
						json.recordsFiltered = json.recordsIncoming;
					}else if(typeCall === 'missed-calls'){
						json.recordsFiltered = json.recordsMissed;
					}else if(typeCall === 'outgoing-calls'){
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
			createdRow(row, data) {
				let detailedIcon = '';
				if (data.DT_RowClass.indexOf("detailed") >= 0) {
					detailedIcon = '<i class="icon caret down"></i>';
				}
				data.typeCall = `${data.typeCall}`;
				if(data.typeCall === '1'){
					$('td', row).eq(0).html('<i class="custom-outgoing-icon-15x15"></i>'+detailedIcon);
				}else if(data.typeCall === '2'){
					$('td', row).eq(0).html('<i class="custom-incoming-icon-15x15"></i>'+detailedIcon);
				}else if(data.typeCall === '3'){
					$('td', row).eq(0).html('<i class="custom-missed-icon-15x15"></i>'+detailedIcon);
				}else{
					$('td', row).eq(0).html(''+detailedIcon);
				}

				$('td', row).eq(1).html(data[0]).addClass('right aligned');
				$('td', row).eq(2)
					.html(data[1])
					.attr('data-phone',data[1])
					.addClass('need-update').addClass('right aligned');
				$('td', row).eq(3)
					.html(data[2])
					.attr('data-phone',data[2])
					.addClass('need-update');

				let duration = data[3];
				if (data.ids !== '') {
					duration += '<i data-ids="' + data.ids + '" class="file alternate outline icon">';
				}

				let lineText = data.line;
				if(data.did !== ""){
					lineText = `${data.line} <a class="ui mini basic label">${data.did}</a>`;
				}
				$('td', row).eq(4).html(lineText).addClass('right aligned');

				$('td', row).eq(5).html(data.waitTime).addClass('right aligned');
				$('td', row).eq(6).html(duration).addClass('right aligned');
				$('td', row).eq(7).html(data.stateCall).addClass('right aligned');
			},

			/**
			 * Draw event - fired once the table has completed a draw.
			 */
			drawCallback(settings) {
				Extensions.updatePhonesRepresent('need-update');
				listenedIDs.forEach(function(id) {
					let element = $(`[id="${id}"]`);
					if (element.length) {
						element.removeClass('warning').addClass('positive');
					}
				});
				let pagination = $(this).closest('.dataTables_wrapper').find('.dataTables_paginate');
				if (settings._iDisplayLength >= settings.fnRecordsDisplay()) {
					pagination.hide();
				} else {
					pagination.show();
				}
			},
			language: SemanticLocalization.dataTableLocalisation,
			ordering: false,
		});
		ModuleExtendedCDRs.dataTable = ModuleExtendedCDRs.$cdrTable.DataTable();
		ModuleExtendedCDRs.dataTable.on('draw', () => {
			ModuleExtendedCDRs.$globalSearch.closest('div').removeClass('loading');
		});
		ModuleExtendedCDRs.$cdrTable.on('click', 'tr.negative', (e) => {
			// let filter = $(e.target).attr('data-phone');
			// if (filter !== undefined && filter !== '') {
			// 	ModuleExtendedCDRs.$globalSearch.val(filter)
			// 	ModuleExtendedCDRs.applyFilter();
			// 	return;
			// }

			let ids = $(e.target).attr('data-ids');
			if (ids !== undefined && ids !== '') {
				window.location = `${globalRootUrl}system-diagnostic/index/?filename=asterisk/verbose&filter=${ids}`;
			}
		});
		// Add event listener for opening and closing details
		ModuleExtendedCDRs.$cdrTable.on('click', 'tr.detailed', (e) => {
			let ids = $(e.target).attr('data-ids');
			if (ids !== undefined && ids !== '') {
				window.location = `${globalRootUrl}system-diagnostic/index/?filename=asterisk/verbose&filter=${ids}`;
				return;
			}
			// let filter = $(e.target).attr('data-phone');
			// if (filter !== undefined && filter !== '') {
			// 	ModuleExtendedCDRs.$globalSearch.val(filter)
			// 	ModuleExtendedCDRs.applyFilter();
			// 	return;
			// }

			const tr = $(e.target).closest('tr');
			const row = ModuleExtendedCDRs.dataTable.row(tr);
			if(row.length === 0){
				return;
			}
			if (tr.hasClass('shown')) {
				// This row is already open - close it
				$('tr[data-row-id="'+tr.attr('id')+'-detailed"').remove();
				tr.removeClass('shown');
			} else {
				// Open this row
				tr.after(ModuleExtendedCDRs.showRecords(row.data() , tr.attr('id')));
				tr.addClass('shown');
				$('tr[data-row-id="'+tr.attr('id')+'-detailed"').each((index, playerRow) => {
					const id = $(playerRow).attr('id');
					return new CDRPlayer(id);
				});
				Extensions.updatePhonesRepresent('need-update');

				listenedIDs.forEach(function(id) {
					let element = $(`[id="${id}"]`);
					if (element.length) {
						element.removeClass('warning').addClass('positive');
					}
					element = $(`[data-row-id="${id}"]`);
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
	changeReportVariant(reportNameID = '', currentVariantId=''){
		if(reportNameID === ''){
			reportNameID = $('#currentReportNameID').val();
			currentVariantId = $('#currentVariantId').val();
		}

		$("[id$='_paginate']").hide();
		$(`table[data-report-name!=""]`).hide();
		$(`table[data-report-name="${reportNameID}"]`).css('width', '').show();
		$(`#${reportNameID}-table_paginate`).show();

		if(reportNameID === 'CallDetails' && ModuleExtendedCDRs.dataTable.page !== undefined){
			ModuleExtendedCDRs.dataTable.page.len(ModuleExtendedCDRs.calculatePageLength()).draw();
		}

		$('#currentReportNameID').val(reportNameID);
		$('#currentVariantId').val(currentVariantId);

		let variantName = '';
		if(currentVariantId !==''){
			variantName = $(`a[data-variant-id="${currentVariantId}"][data-report-id="${reportNameID}"] div.title`).text().trim();
		}else{
			variantName = $(`h4#${reportNameID} div.content`).text().trim();
		}
		$("h1.header div.content").contents().filter(function() {
			return this.nodeType === 3 && this.nodeValue.trim() !== '';
		}).each(function() {
			this.nodeValue = variantName;
		});

		ModuleExtendedCDRs.updateSettings();
	},
	updateSettings(){
		let currentVariantId = $('#currentVariantId').val();
		let reportNameID = $('#currentReportNameID').val();
		let settings = {};
		if(currentVariantId === ''){
			settings = JSON.parse(decodeURIComponent($(`#${reportNameID}`).attr('data-search-text')));
		}else{
			settings = JSON.parse(decodeURIComponent($(`a[data-variant-id="${currentVariantId}"][data-report-id="${reportNameID}"]`).attr('data-search-text')));
		}
		if(settings.dateRangeSelector !== undefined && settings.dateRangeSelector !== ''){
			let periods = ModuleExtendedCDRs.getStandardPeriods();
			let defPeriod = [moment(),moment()];
			if(periods[settings.dateRangeSelector] !== undefined){
				defPeriod = periods[settings.dateRangeSelector];
			}
			ModuleExtendedCDRs.$dateRangeSelector.attr('data-start', defPeriod[0].format('YYYY/MM/DD'));
			ModuleExtendedCDRs.$dateRangeSelector.attr('data-end', moment(defPeriod[1].format('YYYYMMDD')).endOf('day').format('YYYY/MM/DD'));
			ModuleExtendedCDRs.$dateRangeSelector.val(`${defPeriod[0].format('DD/MM/YYYY')} - ${defPeriod[1].format('DD/MM/YYYY') }`);
		}
		if(settings.globalSearch !== undefined) {
			ModuleExtendedCDRs.$globalSearch.val(settings.globalSearch)
		}

		$('#additionalFilter').dropdown('clear');
		if(settings.additionalFilter !== undefined){
			$('#additionalFilter').dropdown('set selected', settings.additionalFilter.split(' '));
		}
		if(settings.typeCall !== undefined){
			$('#typeCall.menu a.item').tab('change tab', settings.typeCall)
		}else{
			$('#typeCall.menu a.item').tab('change tab', 'all-calls')
		}
	},
	/**
	 *
	 */
	updateSyncState(){
		// Выполняем GET-запрос
		let divProgress = $("#sync-progress");
		$.ajax({
			url: `${globalRootUrl}${idUrl}/getState`,
			method: 'GET',
			success: function(response) {
				if(response.stateData.lastId - response.stateData.nowId > 0){
					divProgress.show();
				}else{
					divProgress.hide();
				}
				divProgress.progress({
					total: response.stateData.lastId, value: response.stateData.nowId,
					text: {
						active  : globalTranslate.repModuleExtendedCDRs_syncState
					},
					error: function(jqXHR, textStatus, errorThrown) {
						// Обработка ошибки
						console.error('Ошибка запроса:', textStatus, errorThrown);
					}
				})
			}
		});
	},

	/**
	 * Shows a set of call records when a row is clicked.
	 * @param {Array} data - The row data.
	 * @returns {string} The HTML representation of the call records.
	 */
	showRecords(data, id) {
		let htmlPlayer = '';
		data[4].forEach((record, i) => {
			let srcAudio = '';
			let srcDownloadAudio = '';
			if (!(record.recordingfile === undefined || record.recordingfile === null || record.recordingfile.length === 0)) {
				let recordFileName = encodeURIComponent(record.prettyFilename);
				const recordFileUri = encodeURIComponent(record.recordingfile);
				srcAudio = `/pbxcore/api/cdr/v2/playback?view=${recordFileUri}`;
				srcDownloadAudio = `/pbxcore/api/cdr/v2/playback?view=${recordFileUri}&download=1&filename=${recordFileName}.mp3`;
			}

			htmlPlayer +=`
			<tr id="${record.id}" data-row-id="${id}-detailed" class="warning detailed odd shown" role="row">
				<td></td>
				<td class="right aligned">${record.start}</td>
				<td data-phone="${record.src_num}" class="right aligned need-update">${record.src_num}</td>
			   	<td data-phone="${record.dst_num}" class="left aligned need-update">${record.dst_num}</td>
				<td class="right aligned">			
				</td>
				<td class="right aligned">${record.waitTime}</td>
				<td class="right aligned">
					<i class="ui icon play"></i>
					<audio preload="metadata" id="audio-player-${record.id}" src="${srcAudio}" onplay="ModuleExtendedCDRs.audioPlayHandler(event)"></audio>
					${record.billsec}
					<i class="ui icon download" data-value="${srcDownloadAudio}" onclick="ModuleExtendedCDRs.audioPlayHandler(event)"></i>
				</td>
				<td class="right aligned" data-state-index="${record.stateCallIndex}">${record.stateCall}</td>
			</tr>`
		});
		return htmlPlayer;
	},
	audioPlayHandler(event) {
		let detailRow = $(event.target).closest('tr');
		detailRow.removeClass('warning');
		detailRow.addClass('positive');

		let callIdDetail = detailRow.attr('data-row-id');
		listenedIDs.push(callIdDetail);

		let callId = callIdDetail.replace('-detailed', '');

		let allPositive = true;
		$('[data-row-id="' + callIdDetail + '"]').each(function() {
			if (!$(this).hasClass('positive')) {
				allPositive = false;
				return false;
			}
		});

		if (allPositive) {
			$(`[id="${callId}"]`).addClass('positive');
			listenedIDs.push(callId);
		}

		listenedIDs = [...new Set(listenedIDs)];
	},
	calculatePageLength() {
		// Calculate row height
		let rowHeight = ModuleExtendedCDRs.$cdrTable.find('tbody > tr').first().outerHeight();

		// Calculate window height and available space for table
		const windowHeight = window.innerHeight;
		const headerFooterHeight = 400 ; // Estimate height for header, footer, and other elements

		// Calculate new page length
		return Math.max(Math.floor((windowHeight - headerFooterHeight) / rowHeight), 5);
	},


	additionalExportFunctions(){
		let body = $('body');
		body.on('click', '#add-new-button', function (e) {
			let id = 'none-'+Date.now();
			let newRow 	= $('<tr>').attr('id', id);
			let nameCell = $('<td>').attr('data-label', 'name').addClass('right aligned').html('<div class="ui mini icon input"><input type="text" placeholder="" value=""></div>');
			let usersCell 	= $('<td>').attr('data-label', 'users').html('<div class="ui multiple dropdown">' + $('#users-selector').html() +  '<div>');
			let buttonsCell	= $('<td>').attr('data-label', 'buttons').html('<div class="ui buttons">\n' +
				'                  <button class="compact ui icon basic button" data-action="settings" onclick="ModuleExtendedCDRs.showRuleOptions(\''+id+'\')">\n' +
				'                    <i class="cog icon"></i>\n' +
				'                  </button>\n' +
				'                  <button class="compact ui icon basic button" data-action="remove" onclick="ModuleExtendedCDRs.removeRule(\''+id+'\')">\n' +
				'                    <i class="icon trash red"></i>\n' +
				'                  </button>\n' +
				'                </div>');
			let headersCell = $('<td>').attr('data-label', 'headers').addClass('right aligned').attr('style', 'display: none').html('<div class="ui modal segment" data-id="'+id+'">\n' +
				'                  <i class="close icon"></i>\n' +
				'                    <div class="ui form">\n' +
				'                      <div class="field">\n' +
				'                        <label>HTTP Headers</label>\n' +
				'                        <textarea></textarea>\n' +
				'                      </div>\n' +
				'                      <div class="field">\n' +
				'                        <label>URL</label>\n' +
				'                        <div class="ui icon input"><input data-label="dstUrl" type="text" placeholder="" value=""></div>\n' +
				'                      </div>\n' +
				'                    </div>\n' +
				'                  <div class="actions">\n' +
				'                    <div class="ui positive right labeled icon button">\n' +
				'                      Завершить редактирование\n' +
				'                      <i class="checkmark icon"></i>\n' +
				'                    </div>\n' +
				'                  </div>\n' +
				'                </div>');
			newRow.append(nameCell, usersCell, headersCell, buttonsCell);
			$('#sync-rules').append(newRow);
			$('div.dropdown').dropdown();
		});

		body.on('click', '#save-button', function (e) {
			// Обойдите элементы в цикле
			$("input").each(function(index) {
				$(this).attr('value', $(this).val());
			});

			let data = [];
			let table = $('#sync-rules');
			let rows = table.find('tbody tr');
			rows.each(function() {
				let row = $(this);
				let rowData = {
					id: row.attr('id'),
					name:  row.find('td[data-label="name"] input').val(),
					dstUrl:  $('.ui.modal[data-id="'+row.attr('id')+'"] input[data-label="dstUrl"]').val(),
					users:   row.find('td[data-label="users"] div.dropdown').dropdown('get value'),
					headers: $('.ui.modal[data-id="'+row.attr('id')+'"] textarea').val()
				};
				data.push(rowData);
			});
			$.ajax({
				type: "POST",
				url:  globalRootUrl + idUrl + "/save",
				data: {rules: data},
				success: function(response) {
					console.log("Успешный запрос", response);
					for (let key in response.ruleSaveResult) {
						if (response.ruleSaveResult.hasOwnProperty(key)) {
							let syncRules = $('#sync-rules tr#'+key);
							let html = syncRules.html();
							syncRules.html(html.replace((new RegExp(key, "g")), response.ruleSaveResult[key]));
							syncRules.attr('id', response.ruleSaveResult[key]);
							$('.ui.modal[data-id="'+key+'"]').attr('data-id', response.ruleSaveResult[key]);
						}
					}
				},
				error: function(xhr, status, error) {
					console.error("Ошибка запроса", status, error);
				}
			});
		});
	},
	showRuleOptions(id) {
		$('.ui.modal[data-id="'+id+'"]').modal({closable  : true, }).modal('show');
	},
	removeRule(id) {
		$.ajax({
			type: "POST",
			url:  globalRootUrl + idUrl + "/delete",
			data: {
				table: 'ExportRules',
				id: id
			},
			success: function(response) {
				console.log("Успешный запрос", response);
				$('#sync-rules tr#'+id).remove();
			},
			error: function(xhr, status, error) {
				console.error("Ошибка запроса", status, error);
			}
		});
	},

	/**
	 * Initializes the date range selector.
	 */
	initializeDateRangeSelector() {

		const period = ModuleExtendedCDRs.$dateRangeSelector.attr('data-def-value');
		let defPeriod = [moment(),moment()];
		if(period !== '' && period !== undefined){
			let periods = ModuleExtendedCDRs.getStandardPeriods();
			if(periods[period] !== undefined){
				defPeriod = periods[period];
			}
		}

		const options = {};
		options.ranges = {
			[globalTranslate.repModuleExtendedCDRs_cdr_cal_Today]: [moment(), moment()],
			[globalTranslate.repModuleExtendedCDRs_cdr_cal_Yesterday]: [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
			[globalTranslate.repModuleExtendedCDRs_cdr_cal_LastWeek]: [moment().subtract(6, 'days'), moment()],
			[globalTranslate.repModuleExtendedCDRs_cdr_cal_Last30Days]: [moment().subtract(29, 'days'), moment()],
			[globalTranslate.repModuleExtendedCDRs_cdr_cal_ThisMonth]: [moment().startOf('month'), moment().endOf('month')],
			[globalTranslate.repModuleExtendedCDRs_cdr_cal_LastMonth]: [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
		};
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
			firstDay: 1,
		};
		options.startDate = defPeriod[0];
		options.endDate   = defPeriod[1];
		ModuleExtendedCDRs.$dateRangeSelector.daterangepicker(
			options,
			ModuleExtendedCDRs.cbDateRangeSelectorOnSelect,
		);
	},
	/**
	 * Handles the date range selector select event.
	 * @param {moment.Moment} start - The start date.
	 * @param {moment.Moment} end - The end date.
	 * @param {string} label - The label.
	 */
	cbDateRangeSelectorOnSelect(start, end, label) {
		ModuleExtendedCDRs.$dateRangeSelector.attr('data-start', start.format('YYYY/MM/DD'));
		ModuleExtendedCDRs.$dateRangeSelector.attr('data-end', moment(end.format('YYYYMMDD')).endOf('day').format('YYYY/MM/DD'));
		ModuleExtendedCDRs.$dateRangeSelector.val(`${start.format('DD/MM/YYYY')} - ${end.format('DD/MM/YYYY') }`);
		ModuleExtendedCDRs.applyFilter();
	},

	/**
	 * Applies the filter to the data table.
	 */
	applyFilter() {
		const text  = ModuleExtendedCDRs.getSearchText();
		listenedIDs = [];
		ModuleExtendedCDRs.dataTable.search(text).draw();
		ModuleExtendedCDRs.$outgoingEmployeeCalls.DataTable().search(text).draw();

		ModuleExtendedCDRs.$globalSearch.closest('div').addClass('loading');
	},

	getSearchText(retStandardPeriod = false, disableGlobalSearch = false) {
		let dateRangeSelector = '';
		if(retStandardPeriod === true){
			let periods = ModuleExtendedCDRs.getStandardPeriods();
			$.each(periods,function(index,value){
				if(ModuleExtendedCDRs.$dateRangeSelector.val() ===  `${value[0].format('DD/MM/YYYY')} - ${value[1].format('DD/MM/YYYY')}`){
					dateRangeSelector = index;
				}
			});
		}else{
			dateRangeSelector = ModuleExtendedCDRs.$dateRangeSelector.val();
		}

		let reportNameID = $('#currentReportNameID').val();
		let currentVariantId = $('#currentVariantId').val();
		let minBilSec = $(`h4#${reportNameID}`).attr('data-min-bill-sec');
		if(currentVariantId !== ''){
			minBilSec = $(`a[data-report-id="${reportNameID}"][data-variant-id="${currentVariantId}"]`).attr('data-min-bill-sec');
		}
		const filter = {
			dateRangeSelector: dateRangeSelector,
			minBilSec: minBilSec,
			globalSearch: ModuleExtendedCDRs.$globalSearch.val(),
			typeCall: $('#typeCall a.item.active').attr('data-tab'),
			additionalFilter: $('#additionalFilter').dropdown('get value').replace(/,/g,' '),
		};
		if(disableGlobalSearch === true){
			filter.globalSearch = '';
		}
		return JSON.stringify(filter);
	},

	getStandardPeriods(){
		return {
			'cal_Today': [moment(), moment()],
			'cal_Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
			'cal_LastWeek': [moment().subtract(6, 'days'), moment()],
			'cal_Last30Days': [moment().subtract(29, 'days'), moment()],
			'cal_ThisMonth': [moment().startOf('month'), moment().endOf('month')],
			'cal_LastMonth': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
		}
	},

	saveSearchSettings() {
		let search = ModuleExtendedCDRs.getSearchText(true, true);
		let currentVariantId = $('#currentVariantId').val();
		let reportId = $('#currentReportNameID').val();
		let variantName = '';
		let data = {
			'search[value]': search,
			'reportNameID': reportId,
			'variantId': 	currentVariantId,
			'variantName': 	variantName
		};
		if(currentVariantId !== ''){
			let parent = $(`a[data-variant-id="${currentVariantId}"][data-report-id="${reportId}"]`);
			data.variantName = parent.find('div.title').text().trim();

			data.minBillSec = parent.attr('data-min-bill-sec').trim();
			data.sendingScheduledReport = parent.attr('data-sending-scheduled-report').trim();
			data.dateMonth = parent.attr('data-date-month').trim();
			data.day = parent.attr('data-day').trim();
			data.time = parent.attr('data-time').trim();
			data.email = parent.attr('data-email').trim();
		}

		$.ajax({
			url: `${globalRootUrl}${idUrl}/saveSearchSettings`,
			type: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With': 'XMLHttpRequest',
			},
			data: data,
			success: function(response) {
				if(currentVariantId === ''){
					$(`#${$('#currentReportNameID').val()}`).attr('data-search-text', encodeURIComponent(search));
				}else{
					$(`a[data-variant-id="${currentVariantId}"][data-report-id="${reportId}"]`).attr('data-search-text', encodeURIComponent(search));
				}
			},
			error: function(xhr, status, error) {
				console.error(error);
			}
		});
	},

	startCreateExcelPDF(type) {
		const reportNameID = $('#currentReportNameID').val();
		const encodedSearch = encodeURIComponent(ModuleExtendedCDRs.getSearchText());
		const url = `${window.location.origin}/pbxcore/api/modules/${className}/exportHistory?reportNameID=${reportNameID}&type=${type}&search=${encodedSearch}`;
		window.open(url, '_blank');
	},

	getMaxWidth(data, key) {
		// Получаем максимальную длину содержимого в столбце
		let maxLength = key.length; // начинаем с длины заголовка
		data.forEach(row => {
			let length = (row[key] || '').toString().length;
			if (length > maxLength) maxLength = length;
		});
		return maxLength;
	},

	startDownload(){
		let startTime = ModuleExtendedCDRs.$dateRangeSelector.attr('data-start');
		let endTime   = ModuleExtendedCDRs.$dateRangeSelector.attr('data-end');
		if(startTime === undefined){
			startTime = moment().format('YYYY-MM-DD');
			endTime   =  moment().endOf('day').format('YYYY-MM-DD HH:mm:ss')
		}
		let typeRec = 'inner';
		if($('#allRecord').checkbox('is checked')){
			typeRec = 'all';
		}else if($('#outRecord').checkbox('is checked')){
			typeRec = 'out';
		}
		let numbers = ModuleExtendedCDRs.$globalSearch.val();
		window.open('/pbxcore/api/modules/'+className+'/downloads?start='+startTime+'&end='+endTime+"&numbers="+encodeURIComponent(numbers)+"&type="+typeRec, '_blank');
	},

	startDownloadHistory(){
		let startTime = ModuleExtendedCDRs.$dateRangeSelector.attr('data-start');
		let endTime   = ModuleExtendedCDRs.$dateRangeSelector.attr('data-end');
		if(startTime === undefined){
			startTime = moment().format('YYYY-MM-DD');
			endTime =  moment().endOf('day').format('YYYY-MM-DD HH:mm:ss')
		}
		let typeRec = 'inner';
		if($('#allRecord').checkbox('is checked')){
			typeRec = 'all';
		}else if($('#outRecord').checkbox('is checked')){
			typeRec = 'out';
		}
		let numbers = ModuleExtendedCDRs.$globalSearch.val();
		window.open('/pbxcore/api/modules/'+className+'/downloads-history?start='+startTime+'&end='+endTime+"&numbers="+encodeURIComponent(numbers)+"&type="+typeRec, '_blank');
	},

	/**
	 * Подготавливает список выбора
	 * @param selected
	 * @returns {[]}
	 */
	makeDropdownList(selectType, selected) {
		const values = [
			{
				name: ' --- ',
				value: '',
				selected: ('' === selected),
			}
		];
		$('#'+selectType+' option').each((index, obj) => {
			values.push({
				name: obj.text,
				value: obj.value,
				selected: (selected === obj.value),
			});
		});
		return values;
	},

	/**
	 * Обработка изменения группы в списке
	 */
	changeGroupInList(value, text, choice) {
		let tdInput = $(choice).closest('td').find('input');
		tdInput.attr('data-value', 	value);
		tdInput.attr('value', 		value);
		let currentRowId = $(choice).closest('tr').attr('id');
		let tableName    = $(choice).closest('table').attr('id').replace('-table', '');
		if (currentRowId !== undefined && tableName !== undefined) {
			window[className].sendChangesToServer(tableName, currentRowId);
		}
	},

	/**
	 * Add new Table.
	 */
	initTable(tableName, options) {
		let columns = [];
		let columnsArray4Sort = []
		for (let colName in options['cols']) {
			columns.push( {data: colName});
			columnsArray4Sort.push(colName);
		}
		$('#' + tableName).DataTable( {
			ajax: {
				url: idUrl + options.ajaxUrl + '?table=' +tableName.replace('-table', ''),
				dataSrc: 'data'
			},
			columns: columns,
			paging: true,
			sDom: 'rtip',
			deferRender: true,
			pageLength: 17,
			infoCallback( settings, start, end, max, total, pre ) {
				return '';
			},
			language: SemanticLocalization.dataTableLocalisation,
			ordering: false,
			/**
			 * Builder row presentation
			 * @param row
			 * @param data
			 */
			createdRow(row, data) {
				let cols    = $('td', row);
				let headers = $('#'+ tableName + ' thead tr th');
				for (let key in data) {
					let index = columnsArray4Sort.indexOf(key);
					if(key === 'rowIcon'){
						cols.eq(index).html('<i class="ui ' + data[key] + ' circle icon"></i>');
					}else if(key === 'delButton'){
						let templateDeleteButton = '<div class="ui small basic icon buttons action-buttons">' +
							'<a href="' + window[className].deleteRecordAJAXUrl + '/' +
							data.id + '" data-value = "' + data.DT_RowId + '"' +
							' class="ui button delete two-steps-delete popuped" data-content="' + globalTranslate.bt_ToolTipDelete + '">' +
							'<i class="icon trash red"></i></a></div>';
						cols.eq(index).html(templateDeleteButton);
					}else if(key === 'priority'){
						cols.eq(index).addClass('dragHandle')
						cols.eq(index).html('<i class="ui sort circle icon"></i>');
						// Приоритет устанавливаем для строки.
						$(row).attr('m-priority', data[key]);
					}else{
						let template = '<div class="ui transparent fluid input inline-edit">' +
							'<input colName="'+key+'" class="'+inputClassName+'" type="text" data-value="'+data[key] + '" value="' + data[key] + '"></div>';
						$('td', row).eq(index).html(template);
					}
					if(options['cols'][key] === undefined){
						continue;
					}
					let additionalClass = options['cols'][key]['class'];
					if(additionalClass !== undefined && additionalClass !== ''){
						headers.eq(index).addClass(additionalClass);
					}
					let header = options['cols'][key]['header'];
					if(header !== undefined && header !== ''){
						headers.eq(index).html(header);
					}

					let selectMetaData = options['cols'][key]['select'];
					if(selectMetaData !== undefined){
						let newTemplate = $('#template-select').html().replace('PARAM', data[key]);
						let template = '<input class="'+inputClassName+'" colName="'+key+'" selectType="'+selectMetaData+'" style="display: none;" type="text" data-value="'+data[key] + '" value="' + data[key] + '"></div>';
						cols.eq(index).html(newTemplate + template);
					}
				}
			},
			/**
			 * Draw event - fired once the table has completed a draw.
			 */
			drawCallback(settings) {
				window[className].drowSelectGroup(settings.sTableId);
			},
		} );

		let body = $('body');
		// Клик по полю. Вход для редактирования значения.
		body.on('focusin', '.'+inputClassName, function (e) {
			$(e.target).transition('glow');
			$(e.target).closest('div').removeClass('transparent').addClass('changed-field');
			$(e.target).attr('readonly', false);
		})
		// Отправка формы на сервер по Enter или Tab
		$(document).on('keydown', function (e) {
			let keyCode = e.keyCode || e.which;
			if (keyCode === 13 || keyCode === 9 && $(':focus').hasClass('mikopbx-module-input')) {
				window[className].endEditInput();
			}
		});

		body.on('click', 'a.delete', function (e) {
			e.preventDefault();
			let currentRowId = $(e.target).closest('tr').attr('id');
			let tableName    = $(e.target).closest('table').attr('id').replace('-table', '');
			window[className].deleteRow(tableName, currentRowId);
		}); // Добавление новой строки

		// Отправка формы на сервер по уходу с поля ввода
		body.on('focusout', '.'+inputClassName, window[className].endEditInput);

		// Кнопка "Добавить новую запись"
		$('[id-table = "'+tableName+'"]').on('click', window[className].addNewRow);
	},

	/**
	 * Перемещение строки, изменение приоритета.
	 */
	cbOnDrop(table, row) {
		let priorityWasChanged = false;
		const priorityData = {};
		$(table).find('tr').each((index, obj) => {
			const ruleId = $(obj).attr('id');
			const oldPriority = parseInt($(obj).attr('m-priority'), 10);
			const newPriority = obj.rowIndex;
			if (!isNaN( ruleId ) && oldPriority !== newPriority) {
				priorityWasChanged = true;
				priorityData[ruleId] = newPriority;
			}
		});
		if (priorityWasChanged) {
			$.api({
				on: 'now',
				url: `${globalRootUrl}${idUrl}/changePriority?table=`+$(table).attr('id').replace('-table', ''),
				method: 'POST',
				data: priorityData,
			});
		}
	},

	/**
	 * Окончание редактирования поля ввода.
	 * Не относится к select.
	 * @param e
	 */
	endEditInput(e){
		let $el = $('.changed-field').closest('tr');
		$el.each(function (index, obj) {
			let currentRowId = $(obj).attr('id');
			let tableName    = $(obj).closest('table').attr('id').replace('-table', '');
			if (currentRowId !== undefined && tableName !== undefined) {
				window[className].sendChangesToServer(tableName, currentRowId);
			}
		});
	},

	/**
	 * Добавление новой строки в таблицу.
	 * @param e
	 */
	addNewRow(e){
		let idTable = $(e.target).attr('id-table');
		let table   = $('#'+idTable);
		e.preventDefault();
		table.find('.dataTables_empty').remove();
		// Отправим на запись все что не записано еще
		let $el = table.find('.changed-field').closest('tr');
		$el.each(function (index, obj) {
			let currentRowId = $(obj).attr('id');
			if (currentRowId !== undefined) {
				window[className].sendChangesToServer(currentRowId);
			}
		});
		let id = "new"+Math.floor(Math.random() * Math.floor(500));
		let rowTemplate = '<tr id="'+id+'" role="row" class="even">'+table.find('tr#TEMPLATE').html().replace('TEMPLATE', id)+'</tr>';
		table.find('tbody > tr:first').before(rowTemplate);
		window[className].drowSelectGroup(idTable);
	},

	/**
	 * Обновление select элементов.
	 * @param tableId
	 */
	drowSelectGroup(tableId) {
		$('#' + tableId).find('tr#TEMPLATE').hide();
		let selestGroup = $('.select-group');
		selestGroup.each((index, obj) => {
			let selectType = $(obj).closest('td').find('input').attr('selectType');
			$(obj).dropdown({
				values: window[className].makeDropdownList(selectType, $(obj).attr('data-value')),
			});
		});
		selestGroup.dropdown({
			onChange: window[className].changeGroupInList,
		});

		$('#' + tableId).tableDnD({
			onDrop: window[className].cbOnDrop,
			onDragClass: 'hoveringRow',
			dragHandle: '.dragHandle',
		});
	},

	/**
	 * Удаление строки
	 * @param tableName
	 * @param id - record id
	 */
	deleteRow(tableName, id) {
		let table = $('#'+ tableName+'-table');
		if (id.substr(0,3) === 'new') {
			table.find('tr#'+id).remove();
			return;
		}
		$.api({
			url: window[className].deleteRecordAJAXUrl+'?id='+id+'&table='+tableName,
			on: 'now',
			onSuccess(response) {
				if (response.success) {
					table.find('tr#'+id).remove();
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
	sendChangesToServer(tableName, recordId) {
		let data = { 'pbx-table-id': tableName, 'pbx-row-id':  recordId};
		let notEmpty = false;
		$("tr#"+recordId + ' .' + inputClassName).each(function (index, obj) {
			let colName = $(obj).attr('colName');
			if(colName !== undefined){
				data[$(obj).attr('colName')] = $(obj).val();
				if($(obj).val() !== ''){
					notEmpty = true;
				}
			}
		});

		if(notEmpty === false){
			return;
		}
		$("tr#"+recordId+" .user.circle").removeClass('user circle').addClass('spinner loading');
		$.api({
			url: window[className].saveTableAJAXUrl,
			on: 'now',
			method: 'POST',
			data: data,
			successTest(response) {
				return response !== undefined && Object.keys(response).length > 0 && response.success === true;
			},
			onSuccess(response) {
				if (response.data !== undefined) {
					let rowId = response.data['pbx-row-id'];
					let table = $('#'+response.data['pbx-table-id']+'-table');
					table.find("tr#" + rowId + " input").attr('readonly', true);
					table.find("tr#" + rowId + " div").removeClass('changed-field loading').addClass('transparent');
					table.find("tr#" + rowId + " .spinner.loading").addClass('user circle').removeClass('spinner loading');

					if (rowId !== response.data['newId']){
						$(`tr#${rowId}`).attr('id', response.data['newId']);
					}
				}
			},
			onFailure(response) {
				if (response.message !== undefined) {
					UserMessage.showMultiString(response.message);
				}
				$("tr#" + recordId + " .spinner.loading").addClass('user circle').removeClass('spinner loading');
			},
			onError(errorMessage, element, xhr) {
				if (xhr.status === 403) {
					window.location = globalRootUrl + "session/index";
				}
			}
		});
	},

	/**
	 * Change some form elements classes depends of module status
	 */
	checkStatusToggle() {
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
	applyConfigurationChanges() {
		window[className].changeStatus('Updating');
		$.api({
			url: `${Config.pbxUrl}/pbxcore/api/modules/`+className+`/reload`,
			on: 'now',
			successTest(response) {
				// test whether a JSON response is valid
				return Object.keys(response).length > 0 && response.result === true;
			},
			onSuccess() {
				window[className].changeStatus('Connected');
			},
			onFailure() {
				window[className].changeStatus('Disconnected');
			},
		});
	},

	/**
	 * We can modify some data before form send
	 * @param settings
	 * @returns {*}
	 */
	cbBeforeSendForm(settings) {
		const result = settings;
		result.data = window[className].$formObj.form('get values');
		return result;
	},

	/**
	 * Some actions after forms send
	 */
	cbAfterSendForm() {
		window[className].applyConfigurationChanges();
	},

	/**
	 * Initialize form parameters
	 */
	initializeForm() {
		Form.$formObj = window[className].$formObj;
		Form.url = `${globalRootUrl}${idUrl}/save`;
		Form.validateRules = window[className].validateRules;
		Form.cbBeforeSendForm = window[className].cbBeforeSendForm;
		Form.cbAfterSendForm = window[className].cbAfterSendForm;
		Form.initialize();
	},

	/**
	 * Update the module state on form label
	 * @param status
	 */
	changeStatus(status) {
		switch (status) {
			case 'Connected':
				window[className].$moduleStatus
					.removeClass('grey')
					.removeClass('red')
					.addClass('green');
				window[className].$moduleStatus.html(globalTranslate.module_export_recordsConnected);
				break;
			case 'Disconnected':
				window[className].$moduleStatus
					.removeClass('green')
					.removeClass('red')
					.addClass('grey');
				window[className].$moduleStatus.html(globalTranslate.module_export_recordsDisconnected);
				break;
			case 'Updating':
				window[className].$moduleStatus
					.removeClass('green')
					.removeClass('red')
					.addClass('grey');
				window[className].$moduleStatus.html(`<i class="spinner loading icon"></i>${globalTranslate.module_export_recordsUpdateStatus}`);
				break;
			default:
				window[className].$moduleStatus
					.removeClass('green')
					.removeClass('red')
					.addClass('grey');
				window[className].$moduleStatus.html(globalTranslate.module_export_recordsDisconnected);
				break;
		}
	},
};

$(document).ready(() => {
	window[className].initialize();
});

