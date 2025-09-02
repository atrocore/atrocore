<div class="conditional-disable-options">
    <div class="conditional-disable-options-list-container list-group">
        {{#each itemDataList}}
        <div class="list-group-item">
            <div>
                <div class="options-container" data-key="{{optionsViewKey}}">
                    {{{var optionsViewKey ../this}}}
                </div>
            </div>
            <div>
                <div class="string-container" data-key="{{conditionGroupViewKey}}">
                    {{{var conditionGroupViewKey ../this}}}
                </div>
            </div>
        </div>
        {{/each}}
    </div>
</div>