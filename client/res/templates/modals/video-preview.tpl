<div class="video-container">
    <video src="{{url}}" controls class="video-preview" autoplay width="100%">
</div>

{{#if url}}
<div class="margin"><a href="{{url}}" style="margin-right: 5px" title="{{translate 'Download'}}" download=""><i class="ph ph-download-simple"></i></a><a href="/#File/view/{{fileId}}">{{name}}</a></div>
{{/if}}
