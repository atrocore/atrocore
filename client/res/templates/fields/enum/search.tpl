<select class="form-control search-type {{#if hideSearchType}} hidden {{/if}}  input-sm" name="{{name}}-type">
    {{options searchTypeList searchType field='searchRanges'}}
</select>
<div class="input-container"><input name="{{name}}" type="text"></div>
