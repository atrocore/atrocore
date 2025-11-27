{{#if isNotEmpty}}<span class="label colored-enum">{{#if hasBackground}}<i style="background-color:{{backgroundColor}}"></i>{{/if}}<span>{{translateOption value scope=scope field=name translatedOptions=translatedOptions}}</span></span>
{{else}}
{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}} <span class="pre-label"> </span>{{/if}}
{{/if}}