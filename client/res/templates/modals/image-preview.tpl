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
<div class="margin">
{{translate 'Original'}}: <a href="{{originalUrl}}" target="_blank">{{name}}</a>
<a href="{{originalUrl}}" style="margin-left: 3px" download=""><span class="fas fa-download fa-sm"></span></a>
</div>
{{/if}}
