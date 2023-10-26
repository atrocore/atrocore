{{#if unitFieldName}}
<div class="unit-group input-group">
<input type="text" class="unit-input form-control {{#if params.maxLength}}with-text-length{{/if}}" name="{{name}}" value="{{value}}" autocomplete="off">{{#if params.maxLength}}<div class="text-length-counter"><span class="current-length">0</span> / <span class="max-length">{{params.maxLength}}</span></div>{{/if}}
    <div class="unit-select">
        <select name="{{unitFieldName}}" class="form-control">
            {{{options unitList unitValue translatedOptions=unitListTranslates}}}
        </select>
    </div>
</div>
{{else}}
<input type="text" class="main-element form-control {{#if params.maxLength}}with-text-length{{/if}}" name="{{name}}" value="{{value}}" autocomplete="off">{{#if params.maxLength}}<div class="text-length-counter"><span class="current-length">0</span> / <span class="max-length">{{params.maxLength}}</span></div>{{/if}}
{{/if}}