<div style="text-align: center;" class="image-container">
    <img src="{{url}}" class="image-preview">
</div>

<style>
    {{{path}}} .image-preview {
        max-height: 100%;
        max-width: 100%;
        background-image: linear-gradient(45deg, #cccccc 25%, transparent 25%), linear-gradient(-45deg, #cccccc 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #cccccc 75%), linear-gradient(-45deg, transparent 75%, #cccccc 75%);
        background-size: 20px 20px;
        background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
    }
</style>

{{#if originalUrl}}
<div class="margin"><a href="{{originalUrl}}" style="margin-right: 5px" title="{{translate 'Download'}}" download=""><span class="glyphicon glyphicon-download-alt small"></span></a><a href="/#File/view/{{fileId}}" target="_blank">{{name}}</a></div>
{{/if}}
