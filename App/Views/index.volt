<!-- <div class="ui top attached tabular menu"> -->
<!--     <a class="active item" data-tab="first">{{ t._('repModuleExportRecords_TitleExportTab') }}</a> -->
<!--     <a class="item" data-tab="second">{{ t._('repModuleExportRecords_TitleExportToHttpTab') }}</a> -->
<!-- </div> -->

<form class="ui large grey segment form" id="module-export-records-form">
     <div class="ui bottom attached active tab" data-tab="first">
         <div class="ui grid">
             <div class="ui row" style="padding-bottom:3px">
                 <div class="ui four wide column">
                     <div class="ui fluid  input">
                          <input type="text" data-def-value="{{dateRangeSelector}}" id="date-range-selector" class="form-control">
                     </div>
                 </div>
                 <div id="typeCall" class="ui menu" style="margin: 0;">
                    <a class="item active" data-tab="all-calls">Все <b></b> </a>
                    <a class="item" data-tab="incoming-calls" style="padding-top: 0; padding-bottom: 0;">
                        <i class="custom-incoming-icon"></i>
                        {{ t._('repModuleExportRecords_TitleIncomingCals') }} <b></b>
                    </a>
                    <a class="item" data-tab="missed-calls" style="padding-top: 0; padding-bottom: 0;">
                        <i class="custom-missed-icon"></i>
                        {{ t._('repModuleExportRecords_TitleMissedCals') }} <b></b>
                    </a>
                    <a class="item" data-tab="outgoing-calls" style="padding-top: 0; padding-bottom: 0;">
                        <i class="custom-outgoing-icon"></i>
                        {{ t._('repModuleExportRecords_TitleOutgoingCals') }} <b></b>
                    </a>
                 </div>
                 <div class="ui two wide column">
                     <div class="ui action input">
                        <input type="search" id="globalsearch" placeholder="{{ t._('repModuleExportRecords_FindCallsPlaceholder') }}" aria-controls="KeysTable">
                        <button id="globalsearchButton" class="ui icon basic button"> <i class="green file excel outline icon"></i></button>
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
                   <span class="text">{{ t._('repModuleExportRecords_TitleOtherFilter') }}</span>
                   <div class="menu">
                     <div class="ui icon search input">
                       <i class="search icon"></i>
                       <input type="text" placeholder="{{ t._('repModuleExportRecords_PlaceholderFilter') }}" value="">
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
         <table id="cdr-table" class="ui small very compact single line unstackable table ">
             <thead>
             <tr>
                 <th class="one wide"></th>
                 <th class="two wide right aligned">{{ t._('cdr_ColumnDate') }}</th>
                 <th class="right aligned">{{ t._('cdr_ColumnFrom') }}</th>
                 <th class="">{{ t._('cdr_ColumnTo') }}</th>
                 <th class="one wide">{{ t._('repModuleExportRecords_cdr_ColumnLine') }}</th>
                 <th class="one wide">{{ t._('repModuleExportRecords_cdr_ColumnWaitTime') }}</th>
                 <th class="one wide">{{ t._('cdr_ColumnDuration') }}</th>
                 <th class="one wide right aligned">{{ t._('repModuleExportRecords_cdr_ColumnCallState') }}</th>
             </tr>
             </thead>
             <tbody>
             <tr>
                 <td colspan="5" class="dataTables_empty">{{ t._('dt_TableIsEmpty') }}</td>
             </tr>
             </tbody>
         </table>
<!--          <div class="grouped fields"> -->
<!--             <br> -->
<!--             <label>Что выгружаем:</label> -->
<!--             <div class="field"> -->
<!--               <div id="allRecord" class="ui slider checkbox"> -->
<!--                 <input id="allRecords" type="radio" name="throughput" checked="checked"> -->
<!--                 <label>Все записи</label> -->
<!--               </div> -->
<!--             </div> -->
<!--             <div class="field"> -->
<!--               <div id="outRecord" class="ui slider checkbox"> -->
<!--                 <input type="radio" name="throughput"> -->
<!--                 <label>Внешние разговоры</label> -->
<!--               </div> -->
<!--             </div> -->
<!--             <div class="field"> -->
<!--               <div id="innerRecord" class="ui slider checkbox"> -->
<!--                 <input type="radio" name="throughput"> -->
<!--                 <label>Внутренние разговоры</label> -->
<!--               </div> -->
<!--             </div> -->
<!--           </div> -->
<!--           <button class="ui primary button" id="downloadBtn" onclick='ModuleExportRecords.startDownload();'> -->
<!--               Скачать записи -->
<!--           </button> -->
<!--           <button class="ui primary button" id="downloadBtn" onclick='ModuleExportRecords.startDownloadHistory();'> -->
<!--               Скачать историю звонков (*.xls) -->
<!--           </button> -->
    </div>
<!--     <div class="ui bottom attached tab" data-tab="second"> -->
<!--        <div id="users-selector" class="ui multiple dropdown" style="display: none"> -->
<!--           <input type="hidden" name="filters"> -->
<!--           <i class="filter icon"></i> -->
<!--           <span class="text"></span> -->
<!--           <div class="menu"> -->
<!--             <div class="ui icon search input"> -->
<!--               <i class="search icon"></i> -->
<!--               <input type="text" placeholder="Search tags..."> -->
<!--             </div> -->
<!--             <div class="scrolling menu"> -->
<!--               {% for user in users %} -->
<!--               <div class="item" data-value="{{ user['userid'] }}">{{ user['number'] }} "{{ user['callerid'] }} "</div> -->
<!--               {% endfor %} -->
<!--             </div> -->
<!--           </div> -->
<!--         </div> -->

<!--         <a id="add-new-button" class="ui blue button"><i class="add circle icon"></i>Добавить правило</a> -->
<!--         <a id="save-button" class="ui blue button"><i class="add circle icon"></i>Сохранить</a> -->
<!--         <table id="sync-rules" class="ui celled table"> -->
<!--           <thead> -->
<!--             <tr> -->
<!--               <th class="collapsing">Наименование</th> -->
<!--               <th class="center aligned" >Сотрудники</th> -->
<!--               <th class="collapsing right aligned" style="display: none"></th> -->
<!--               <th class="collapsing right aligned"></th> -->
<!--             </tr> -->
<!--           </thead> -->
<!--           <tbody> -->
<!--             {% for rule in rules %} -->
<!--             <tr id="{{ rule['id'] }}"> -->
<!--               <td data-label="name" class="right aligned"> -->
<!--                   <div class="ui mini icon input"><input type="text" placeholder="" value="{{ rule['name'] }}"></div> -->
<!--               </td> -->
<!--               <td data-label="users"> -->
<!--                   <div class="ui multiple dropdown"> -->
<!--                       <input type="hidden" name="filters" value="{{ rule['users'] }}"> -->
<!--                       <i class="filter icon"></i> -->
<!--                       <span class="text"></span> -->
<!--                       <div class="menu"> -->
<!--                         <div class="ui icon search input"> -->
<!--                           <i class="search icon"></i> -->
<!--                           <input type="text" placeholder="Search tags..."> -->
<!--                         </div> -->
<!--                         <div class="scrolling menu"> -->
<!--                           {% for user in users %} -->
<!--                           <div class="item" data-value="{{ user['userid'] }}">{{ user['number'] }} "{{ user['callerid'] }} "</div> -->
<!--                           {% endfor %} -->
<!--                         </div> -->
<!--                       </div> -->
<!--                   </div> -->
<!--               </td> -->
<!--               <td data-label="headers" class="right aligned" style="display: none" > -->
<!--                 <div class="ui modal segment" data-id="{{ rule['id'] }}"> -->
<!--                   <i class="close icon"></i> -->
<!--                     <div class="ui form"> -->
<!--                       <div class="field"> -->
<!--                         <label>HTTP Headers</label> -->
<!--                         <textarea>{{ rule['headers'] }}</textarea> -->
<!--                       </div> -->
<!--                       <div class="field"> -->
<!--                         <label>URL</label> -->
<!--                         <div class="ui icon input"><input type="text" data-label="dstUrl" placeholder="" value="{{ rule['dstUrl'] }}"></div> -->
<!--                       </div> -->
<!--                     </div> -->
<!--                   <div class="actions"> -->
<!--                     <div class="ui positive right labeled icon button"> -->
<!--                       Завершить редактирование -->
<!--                       <i class="checkmark icon"></i> -->
<!--                     </div> -->
<!--                   </div> -->
<!--                 </div> -->
<!--               </td> -->
<!--               <td data-label="buttons" class="right aligned"> -->
<!--                 <div class="ui buttons"> -->
<!--                   <button class="compact ui icon basic button" data-action="settings" onclick="ModuleExportRecords.showRuleOptions('{{ rule['id'] }}')"> -->
<!--                     <i class="cog icon"></i> -->
<!--                   </button> -->
<!--                   <button class="compact ui icon basic button" data-action="remove" onclick="ModuleExportRecords.removeRule('{{ rule['id'] }}')"> -->
<!--                     <i class="icon trash red"></i> -->
<!--                   </button> -->
<!--                 </div> -->
<!--               </td> -->
<!--             </tr> -->
<!--             {% endfor %} -->
<!--           </tbody> -->
<!--         </table> -->
<!--     </div> -->

</form>

 <div class="ui bottom aattached active tab" data-tab="all-calls"></div>
 <div class="ui bottom aattached active tab" data-tab="incoming-calls"></div>
 <div class="ui bottom aattached active tab" data-tab="missed-calls"></div>
 <div class="ui bottom aattached active tab" data-tab="outgoing-calls"></div>