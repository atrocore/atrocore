{{#if hasIcon}}
<a href="?entryPoint=download&id={{id}}" target="_blank">
<span class="fiv-cla fiv-icon-{{extension}} fiv-size-lg"></span>
</a>
{{else}}
<a data-action="showImagePreview" data-id="{{id}}" href="{{originPath}}">
    <img src="{{thumbnailPath}}" style="max-width: 100px;"/>
</a>
{{/if}}