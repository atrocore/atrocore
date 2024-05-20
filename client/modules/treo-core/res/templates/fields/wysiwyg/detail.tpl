{{#unless isPlain}}
    {{#if useIframe}}
    <iframe frameborder="0" style="width: 100%; overflow-x: hidden; overflow-y: hidden;" class="hidden"></iframe>
    {{else}}
    {{#if isNotEmpty}}<div class="html-container" data-name="{{name}}">{{{value}}}</div>{{else}}{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}{{/if}}
    {{/if}}
{{else}}
{{#if isNotEmpty}}<div class="plain complex-text hidden">{{complexText value}}</div>{{else}}{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}{{/if}}
{{/unless}}
