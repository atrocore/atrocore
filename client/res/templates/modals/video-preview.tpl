<div class="video-container">
    <video src="{{url}}" controls class="video-preview" autoplay width="100%">
</div>

{{#if url}}
<div class="margin"><a href="{{url}}" style="margin-right: 5px" title="{{translate 'Download'}}" download=""><span class="glyphicon glyphicon-download-alt small"></span></a><a href="/#File/view/{{fileId}}">{{name}}</a></div>
{{/if}}
