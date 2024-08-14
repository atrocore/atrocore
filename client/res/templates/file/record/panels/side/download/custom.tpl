<div class="col-sm-12">
    <input name="downloadType" type="radio" value="custom" id="downloadType-custom">
    <label class="control-label" for="downloadType-custom">Custom Download</label>
</div>
<div class="additional-panel mt-51" style="display: none">
    <div class="cell col-sm-4 form-group" data-name="mode">
        <label class="control-label" data-name="name">
            <span class="label-text">{{translate "Mode" scope="Global"}}</span>
        </label>
        <div class="field" data-name="mode">
            {{{mode}}}
        </div>
    </div>

    <div class="cell col-sm-4 form-group" data-name="width">
        <label class="control-label" data-name="height">
            <span class="label-text">{{translate "Width" scope="Global"}}</span>
        </label>
        <div class="field" data-name="width">
            {{{width}}}
        </div>
    </div>

    <div class="cell col-sm-4 form-group" data-name="height">
        <label class="control-label" data-name="height">
            <span class="label-text">{{translate "Height" scope="Global"}}</span>
        </label>
        <div class="field" data-name="height">
            {{{height}}}
        </div>
    </div>

    <div class="cell col-sm-6 form-group" data-name="format">
        <label class="control-label" data-name="format">
            <span class="label-text">{{translate "Format" scope="Global"}}</span>
        </label>
        <div class="field" data-name="format">
            {{{format}}}
        </div>
    </div>

    <div class="cell col-sm-6 form-group" data-name="quality">
        <label class="control-label" data-name="quality">
            <span class="label-text">{{translate "Quality" scope="Global"}}</span>
        </label>
        <div class="field" data-name="quality">
            {{{quality}}}
        </div>
    </div>
</div>
