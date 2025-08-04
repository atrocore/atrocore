{{#if isNotEmpty}}<span class="label colored-enum"><i style="background-color:{{backgroundColor}}"></i><span>{{translateOption value scope=scope field=name translatedOptions=translatedOptions}}</span></span>
{{else}}
{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}} <span class="pre-label"> </span>{{/if}}
{{/if}}