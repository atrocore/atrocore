<div class="col-sm-12">
    <input name="downloadType" type="radio" value="renditions">
    <span class="control-label">Rendition</span>
</div>
<div class="additional-panel mt-5" style="display: none">
    <div class="col-sm-12">
        <div class="field" data-name="type">
            <select name="type" class="form-control main-element">
                {{#each collection.models}}
                <option value="{{get this "id"}}">
                    {{get this "name"}}
                </option>
                {{/each}}
            </select>
        </div>
    </div>
</div>