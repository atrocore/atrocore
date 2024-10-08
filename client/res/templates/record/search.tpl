
<div class="row search-row">
    <div class="form-group {{#if isModalDialog}}col-md-12 col-sm-12{{/if}}">
        <div class="input-group">
            <div class="input-group-btn left-dropdown{{#unless leftDropdown}} hidden{{/unless}}">
                <button type="button" class="btn btn-default dropdown-toggle filters-button {{#if filtersDisabled}}disabled{{/if}}" title="{{translate 'Filter'}}" data-toggle="dropdown" tabindex="-1">
                    <span class="filters-label"></span>
                    <span class="caret"></span>
                </button>
                <ul class="dropdown-menu pull-left filter-menu">
                    <li class="filter-menu-closer"></li>
                    <li><a class="preset" tabindex="-1" href="javascript:" data-name="" data-action="selectPreset"><div>{{translate 'All'}}</div></a></li>
                    {{#each presetFilterList}}
                    <li><a class="preset" tabindex="-1" href="javascript:" data-name="{{name}}" data-action="selectPreset"><div>{{#if label}}{{label}}{{else}}{{translate name category='presetFilters' scope=../../entityType}}{{/if}}</div></a></li>
                    {{/each}}
                    <li class="divider preset-control hidden"></li>


                    <li class="preset-control remove-preset hidden"><a tabindex="-1" href="javascript:" data-action="removePreset">{{translate 'Remove Filter'}}</a></li>
                    <li class="preset-control save-preset hidden"><a tabindex="-1" href="javascript:" data-action="savePreset">{{translate 'Save Filter'}}</a></li>

                    {{#if queryBuilderFilter}}
                    {{else}}
                    {{#if advancedFields.length}}
                    <li class="divider"></li>

                    <li class="dropdown-submenu">
                        <a href="javascript:" class="add-filter-button" tabindex="-1">
                            {{translate 'Add Field'}}
                        </a>
                        <ul class="dropdown-menu show-list filter-list">
                            {{#each advancedFields}}
                            <li data-name="{{name}}" class="{{#if checked}}hide{{/if}}"><a href="javascript:" class="add-filter" data-action="addFilter" data-name="{{name}}">{{label}}</a></li>
                            {{/each}}
                        </ul>
                    </li>
                    {{/if}}
                    {{#if additionalFilters}}
                    {{#each additionalFilters}}
                    <li class="divider"></li>
                    <li class="dropdown-submenu">
                        <a href="javascript:" data-filter-name="{{name}}" tabindex="-1">{{label}}</a>
                    </li>
                    {{/each}}
                    {{/if}}
                    {{/if}}
                    {{#if boolFilterListLength}}
                    <li class="divider"></li>
                    {{/if}}

                    {{#each boolFilterListComplex}}
                    <li class="checkbox{{#if hidden}} hidden{{/if}}"><label><input type="checkbox" data-role="boolFilterCheckbox" name="{{name}}" {{#ifPropEquals ../bool name true}}checked{{/ifPropEquals}}> {{translate name scope=../entityType category='boolFilters'}}</label></li>
                    {{/each}}
                </ul>
            </div>
            {{#unless textFilterDisabled}}<input type="text" class="form-control text-filter" placeholder="{{translate 'typeAndPressEnter'}}" name="textFilter" value="{{textFilter}}" tabindex="1">{{/unless}}
            <div class="input-group-btn">
                <button type="button" class="btn btn-default reset filter-btn" data-action="reset" title="{{translate 'Reset'}}">
                    <span class="fas fa-times"></span>
                </button>
                <button type="button" class="btn btn-primary search btn-icon btn-icon-x-wide filter-btn" data-action="search" title="{{translate 'Search'}}">
                    <span class="fas fa-search"></span>
                </button>
            </div>
        </div>
    </div>
    {{#if hasViewModeSwitcher}}
    <div class="form-group col-md-6 col-sm-5">
        <div class="btn-group view-mode-switcher-buttons-group">
            {{#each viewModeDataList}}
            <button type="button" data-name="{{name}}" data-action="switchViewMode" class="btn btn-sm btn-icon btn-default{{#ifEqual name ../viewMode}} active{{/ifEqual}}" title="{{title}}"><span class="{{iconClass}}"></span></button>
            {{/each}}
        </div>
    </div>
    {{/if}}
</div>

<div class="advanced-filters-bar" style="margin-bottom: 12px;"></div>
{{#if queryBuilderFilter}}
{{#if hasAttributeButton}}
<button class="btn-link btn" data-action="add-attribute-filter"><div>{{translate 'Add Attribute'}}</div></button>
{{/if}}
<div class="query-builder"></div>
{{else}}
<div class="row advanced-filters grid-auto-fill-sm">
    {{#each filterDataList}}
    <div class="filter filter-{{name}} col-sm-4 col-md-3" data-name="{{name}}">
        {{{var key ../this}}}
    </div>
    {{/each}}
</div>
{{/if}}
{{#if queryBuilderFilterAvailable}}
<div class="row new-filter-container">
    <div class="cell form-group">
        <label class="control-label">{{translate 'Query Builder Filter'}}</label>
        <div class="field">{{{hasQueryBuilderFilter}}}</div>
    </div>
</div>
{{/if}}

<div class="row filter-actions">
    <button class="btn-link btn" data-action="search"><div>{{translate 'Apply filter'}}</div></button>
    <span class="pipeline">|</span>
    <button class="btn-link btn" data-action="reset-filter"><div>{{translate 'Reset filter'}}</div></button>
</div>