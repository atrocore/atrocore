{{#if icon}}
<div class="row">
    <div class="col-sm-12" style="text-align: center">
        <span class="fiv-cla fiv-icon-{{icon}} fiv-size-lg"></span>
    </div>
</div>
{{else}}
<div class="row">
    <div class="col-sm-12" style="text-align: center">
        {{#if hasVideoPlayer}}
          <video src="{{originPath}}" controls width="100%"></video>
        {{else}}
          {{#if isImage}}
            <a data-action="showImagePreview" data-id="{{fileId}}" href="{{originPath}}">
              <img src="{{thumbnailPath}}" class="img-fluid image-preview" alt="Responsive image">
            </a>
          {{/if}}
        {{/if}}
    </div>
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
{{/if}}