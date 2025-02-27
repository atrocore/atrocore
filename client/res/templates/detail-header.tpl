<!--<div>-->
<!--    {{{header}}}-->
<!--</div>-->


<!--<div class="detail-button-container button-container record-buttons clearfix">-->
<!--    {{#if hasButtons}}-->
<!--        <div class="btn-group pull-left" role="group">-->
<!--            {{#each buttonList}}{{button name scope=../../entityType label=label style=style hidden=hidden html=html}}{{/each}}-->
<!--            {{#if dropdownItemList}}-->
<!--                <button type="button" class="btn btn-default dropdown-toggle dropdown-item-list-button{{#if dropdownItemListEmpty}} hidden{{/if}}" data-toggle="dropdown">-->
<!--                    <span class="caret"></span>-->
<!--                </button>-->
<!--                <ul class="dropdown-menu pull-left">-->
<!--                    {{#each dropdownItemList}}-->
<!--                        {{#if divider}}-->
<!--                            <li class="divider"></li>-->
<!--                        {{ else if preloader }}-->
<!--                            <li class="preloader"><a href="javascript:"><img class="preloader" style="height:12px;margin-top: 5px" src="client/img/atro-loader.svg"></a> </li>-->
<!--                        {{else}}-->
<!--                            <li class="{{#if hidden}}hidden{{/if}}"><a href="javascript:" class="action" data-action="{{name}}" {{#if id}}data-id="{{id}}"{{/if}}>{{#if html}}{{{html}}}{{else}}{{translate label scope=../../entityType}}{{/if}}</a></li>-->
<!--                        {{/if}}-->
<!--                    {{/each}}-->
<!--                </ul>-->
<!--            {{/if}}-->
<!--            {{#if additionalButtons}}-->
<!--                {{#each additionalButtons}}-->
<!--                    {{# if preloader }}-->
<!--                        <a class="preloader" style="margin-left: 20px;display: none" href="javascript:"><img class="preloader" style="height:12px;margin-top: 5px" src="client/img/atro-loader.svg"></a>-->
<!--                    {{else}}-->
<!--                        <button type="button" class="btn btn-default additional-button action" {{#if cssStyle }} style="{{cssStyle}}" {{/if}} {{#if tooltip}} title="{{tooltip}}"{{/if}} data-action="{{action}}" {{#if id}}data-id="{{id}}"{{/if}}>{{#if html}}{{{html}}}{{else}}{{label}}{{/if}}</button>-->
<!--                    {{/if}}-->
<!--                {{/each}}-->
<!--            {{/if}}-->
<!--        </div>-->
<!--    {{/if}}-->
<!--    <div class="header-buttons-container">-->
<!--        {{{headerButtons}}}-->
<!--    </div>-->
<!--    <div class="panel-navigation panel-left pull-left">{{{panelDetailNavigation}}}</div>-->
<!--    <div class="clearfix"></div>-->
<!--</div>-->
<!--<div class="detail-button-container button-container edit-buttons hidden clearfix">-->
<!--    <div class="btn-group pull-left" role="group">-->
<!--        {{#each buttonEditList}}{{button name scope=../../entityType label=label style=style hidden=hidden html=html}}{{/each}}-->
<!--        {{#if dropdownEditItemList}}-->
<!--            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">-->
<!--                <span class="caret"></span>-->
<!--            </button>-->
<!--            <ul class="dropdown-menu pull-left">-->
<!--                {{#each dropdownEditItemList}}-->
<!--                    <li class="{{#if hidden}}hidden{{/if}}"><a href="javascript:" class="action" data-action="{{name}}">{{#if html}}{{{html}}}{{else}}{{translate label scope=../../entityType}}{{/if}}</a></li>-->
<!--                {{/each}}-->
<!--            </ul>-->
<!--        {{/if}}-->
<!--        {{#if additionalEditButtons}}-->
<!--            {{#each additionalEditButtons}}-->
<!--                <button type="button" class="btn btn-default additional-button action"{{#if tooltip}} title="{{tooltip}}"{{/if}} data-action="{{action}}" {{#if id}}data-id="{{id}}"{{/if}}>{{#if html}}{{{html}}}{{else}}{{label}}{{/if}}</button>-->
<!--            {{/each}}-->
<!--        {{/if}}-->
<!--    </div>-->
<!--    <div class="panel-navigation panel-right pull-left">{{{panelEditNavigation}}}</div>-->
<!--</div>-->