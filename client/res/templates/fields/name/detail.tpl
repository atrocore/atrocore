{{#if isNotEmpty}}<a href="?entryPoint=download&showInline=false&id={{attachmentId}}" target="_blank">{{value}}</a>{{else}}
{{#if valueIsSet}}{{{translate 'None'}}}{{else}}...{{/if}}
{{/if}}