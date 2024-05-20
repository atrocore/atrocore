{{#if isNotEmpty}}
<span class="complex-text">{{complexText valueWithoutTags}}</span>
{{else}}
{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}
{{/if}}