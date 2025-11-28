{{#if isNotEmpty}}
{{translateOption value scope=scope field=name translatedOptions=translatedOptions}}
{{else}}
{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}} <span class="pre-label"> </span>{{/if}}
{{/if}}