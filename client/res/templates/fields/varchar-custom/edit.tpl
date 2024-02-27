<input
        type="text"
        class="main-element form-control"
        name="{{name}}" value="{{value}}"
        {{#if params.maxLength}}maxlength="{{params.maxLength}}"{{/if}}
        {{#if readOnly}}readonly="readonly"{{/if}}
        autocomplete="off">

