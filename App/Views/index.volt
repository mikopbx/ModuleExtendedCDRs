<div id="menu-reports"  style="display: none;" class="ui fluid popup bottom left transition hidden">
  <input id="currentReportNameID" type="hidden" name="filters" value="{{currentReportNameID}}">
  <input id="currentVariantId" type="hidden" name="filters" value="{{currentVariantId}}">

  <div class="ui four column relaxed equal height divided grid">
       {% for variantId,variantsData in mainReports %}
        <div class="six wide column">
            <h4 id="{{ variantId }}" class="ui header" data-variant-id="{{ variantsData['variantId'] }}" data-search-text="{{ variantsData['searchText'] }}" data-is-main="{{ variantsData['isMain'] }}" data-min-bill-sec="{{ variantsData['minBillSec'] }}" style="cursor: pointer;">
                {% if variantsData['isMain'] == '1' %}
                    <i class="small star yellow icon"></i>
                {% else %}
                    <i class="small star yellow outline icon"></i>
                {% endif %}
                <i class="small copy green outline icon"></i>
                <div class="content">
                  {{ variantsData['variantName'] }}
                </div>
            </h4>
          <div class="ui link list">
               {% for variantsAdditionalData in variants[variantId] %}
                    <a class="item" data-variant-id="{{ variantsAdditionalData['variantId'] }}" data-search-text="{{ variantsAdditionalData['searchText'] }}"
                        data-report-id="{{variantId}}" data-is-main="{{ variantsAdditionalData['isMain'] }}"
                        data-sending-scheduled-report="{{ variantsAdditionalData['sendingScheduledReport'] }}" data-min-bill-sec="{{ variantsAdditionalData['minBillSec'] }}"
                        data-date-month="{{ variantsAdditionalData['dateMonth'] }}" data-day="{{ variantsAdditionalData['day'] }}" data-time="{{ variantsAdditionalData['time'] }}"
                        data-email="{{ variantsAdditionalData['email'] }}"
                        >
                        {% if variantsAdditionalData['isMain'] == '1' %}
                            <i class="small star yellow icon" style="padding-top: 3px"></i>
                        {% else %}
                            <i class="small star outline yellow icon" style="padding-top: 3px"></i>
                        {% endif %}
                        <i class="small edit outline icon" style="padding-top: 3px"></i>
                        <i class="small trash alternate outline outline red icon" style="padding-top: 3px"></i>
                        <div class="content">
                        	<div class="title">{{ variantsAdditionalData['variantName'] }}</div>
                        </div>
                    </a>
               {% endfor %}
          </div>
          <form data-report-id="{{variantId}}" class="ui mini form" style="display: none;">
            <input type="hidden" name="reportNameID" value="{{variantId}}">
            <input type="hidden" name="variantId" value="{{ variantsAdditionalData['variantId'] }}">

            <div class="field">
              <label>{{t._('repModuleExtendedCDRs_Form_titleReport')}}</label>
              <input type="text" name="title" placeholder="{{ t._('repModuleExtendedCDRs_Form_titleReport') }}...">
            </div>
            {% if variantId == 'CallDetails' %}
            <div class="field" style="display: none;">
            {% else %}
            <div class="field">
            {% endif %}

              <label>{{ t._('repModuleExtendedCDRs_Form_minBillSec') }}</label>
              <div class="ui right labeled input">
                <input type="text" name="minBillSec" placeholder="0">
                <div class="ui basic label">
                  {{ t._('repModuleExtendedCDRs_Form_minBillSec_s') }}
                </div>
              </div>
            </div>
            {% if accessData['fullAccess'] == '1' %}
            <div>
            {% else %}
            <div style="display: none;">
            {% endif %}
                <div class="field">
                  <div class="ui checkbox">
                    <input type="checkbox" name="sendingScheduledReport" tabindex="0" class="hidden">
                    <label>{{ t._('repModuleExtendedCDRs_Form_SendingScheduledReport') }}</label>
                  </div>
                </div>
                <div class="field">
                  <label>{{ t._('repModuleExtendedCDRs_Form_DateMonth') }}</label>
                  <input type="text" name="dateMonth" placeholder="1-31">
                </div>
                <div class="field">
                  <label>{{ t._('repModuleExtendedCDRs_Form_Day') }}</label>
                  <input type="text" name="day" placeholder="1-7">
                </div>
                <div class="field">
                  <label>{{ t._('repModuleExtendedCDRs_Form_Time') }}</label>
                  <input type="text" name="time" placeholder="09:35">
                </div>
                <div class="field">
                  <label>Email</label>
                  <input type="text" name="email" placeholder="test@test.ru test2@test.ru test3@test.ru">
                </div>
            </div>
            <br>
            <button class="ui button" data-action="save" type="button">Сохранить</button>
          </form>
        </div>
       {% endfor %}
  </div>
  </div>
</div>

<form class="ui large grey  form" id="module-extended-cdr-form">
    <div id="sync-progress" class="ui progress">
      <div class="bar"></div>
      <div class="label"></div>
    </div>

    <br>
    <div class="ui three column grid">
        <div class="ui row" style="padding-bottom:3px">
             <div class="ui four wide column">
                 <div class="ui fluid  input">
                      <input type="text" data-def-value="{{dateRangeSelector}}" id="date-range-selector" class="form-control">
                 </div>
             </div>
             <div class="ui column" style="display: inline-block; width: auto;">
                 <div id="typeCall" class="ui menu" style="margin: 0;">
                    <a class="item active" data-tab="all-calls">Все <b></b> </a>
                    <a class="item" data-tab="incoming-calls" style="padding-top: 0; padding-bottom: 0;">
                        <i class="custom-incoming-icon"></i>
                        {{ t._('repModuleExtendedCDRs_TitleIncomingCals') }} <b></b>
                    </a>
                    <a class="item" data-tab="missed-calls" style="padding-top: 0; padding-bottom: 0;">
                        <i class="custom-missed-icon"></i>
                        {{ t._('repModuleExtendedCDRs_TitleMissedCals') }} <b></b>
                    </a>
                    <a class="item" data-tab="outgoing-calls" style="padding-top: 0; padding-bottom: 0;">
                        <i class="custom-outgoing-icon"></i>
                        {{ t._('repModuleExtendedCDRs_TitleOutgoingCals') }} <b></b>
                    </a>
                 </div>
             </div>
             <div class="ui two wide column" >
                 <div class="ui action input">
                    <input type="search" id="globalsearch" placeholder="{{ t._('repModuleExtendedCDRs_FindCallsPlaceholder') }}" aria-controls="KeysTable">
                    <button id="createExcelButton" class="ui icon basic button"> <i class="green file excel outline icon"></i></button>
                    <button id="createPdfButton" class="ui icon basic button"> <i class="red file pdf outline icon"></i></button>
                    <button id="downloadRecords" class="ui icon basic button"> <i class="download icon"></i></button>
                    <button type="button" id="saveSearchSettings" class="ui icon basic button">
                         <i class="blue save outline icon"></i>
                    </button>
                 </div>
             </div>
         </div>
         <div class="ui row" style="padding-top:0; padding-left:9px">
             <div id='additionalFilter' class="ui multiple dropdown">
               <input type="hidden" name="filters" value="{{additionalFilterString}}">
               <i class="filter icon"></i>
               {% for filter in additionalFilter %}
               <a class="ui label transition visible" data-value="{{ filter['number'] }}" style="display: inline-block !important;">
                    <i class="user icon"></i>{{ filter['name'] }} <i class="delete icon"></i>
               </a>
               {% endfor %}
               <span class="text">{{ t._('repModuleExtendedCDRs_TitleOtherFilter') }}</span>
               <div class="menu">
                 <div class="ui icon search input">
                   <i class="search icon"></i>
                   <input type="text" placeholder="{{ t._('repModuleExtendedCDRs_PlaceholderFilter') }}" value="">
                 </div>
                 <div class="scrolling menu">
                    {% for group in groups %}
                    <div class="item" data-value="group_{{ group['id'] }}">
                    <i class="users icon"></i>
                    Отдел: {{ group['name'] }}
                    </div>
                    {% endfor %}
                    <h4 class="ui horizontal divider header"></h4>
                    {% for user in users %}
                    <div class="item" data-value="{{ user['number'] }}">
                    <i class="user icon"></i>
                    {{ user['callerid'] }} ({{ user['number'] }})
                    </div>
                    {% endfor %}
                 </div>

               </div>

             </div>
         </div>
     </div>
     <table id="OutgoingEmployeeCalls-table" data-report-name="OutgoingEmployeeCalls" class="ui small very compact single line unstackable celled striped table ">
         <thead>
         <tr>
             <th class="three">{{ t._('repModuleExtendedCDRs_outgoingEmployeeCalls_callerId') }}</th>
             <th class="three wide">{{ t._('repModuleExtendedCDRs_outgoingEmployeeCalls_number') }}</th>
             <th class="one wide">{{ t._('repModuleExtendedCDRs_outgoingEmployeeCalls_billHourCalls') }}</th>
             <th class="one wide">{{ t._('repModuleExtendedCDRs_outgoingEmployeeCalls_billMinCalls') }}</th>
             <th class="one wide">{{ t._('repModuleExtendedCDRs_outgoingEmployeeCalls_billSecCalls') }}</th>
             <th class="one wide">{{ t._('repModuleExtendedCDRs_outgoingEmployeeCalls_countCalls') }}</th>
         </tr>
         </thead>
         <tbody>
         <tr>
             <td colspan="5" class="dataTables_empty">{{ t._('dt_TableIsEmpty') }}</td>
         </tr>
         </tbody>
     </table>
     <br>
     <table id="cdr-table" data-report-name="CallDetails" class="ui small very compact single line unstackable table ">
         <thead>
         <tr>
             <th class="one wide"></th>
             <th class="two wide right aligned">{{ t._('cdr_ColumnDate') }}</th>
             <th class="right aligned">{{ t._('cdr_ColumnFrom') }}</th>
             <th class="">{{ t._('cdr_ColumnTo') }}</th>
             <th class="one wide">{{ t._('repModuleExtendedCDRs_cdr_ColumnLine') }}</th>
             <th class="one wide">{{ t._('repModuleExtendedCDRs_cdr_ColumnWaitTime') }}</th>
             <th class="one wide">{{ t._('cdr_ColumnDuration') }}</th>
             <th class="one wide right aligned">{{ t._('repModuleExtendedCDRs_cdr_ColumnCallState') }}</th>
         </tr>
         </thead>
         <tbody>
         <tr>
             <td colspan="5" class="dataTables_empty">{{ t._('dt_TableIsEmpty') }}</td>
         </tr>
         </tbody>
     </table>
</form>
 <div class="ui bottom aattached active tab" data-tab="all-calls"></div>
 <div class="ui bottom aattached active tab" data-tab="incoming-calls"></div>
 <div class="ui bottom aattached active tab" data-tab="missed-calls"></div>
 <div class="ui bottom aattached active tab" data-tab="outgoing-calls"></div>