
<select class="form-control search-type input-sm" name="{{name}}-type">
    {{options searchTypeList searchType field='dateSearchRanges'}}
</select>
<div class="input-group primary">
    <input class="main-element form-control input-sm" type="text" name="{{name}}" value="{{searchData.dateValue}}" autocomplete="off">
    <span class="input-group-btn">
        <button type="button" class="btn btn-default btn-icon btn-sm date-picker-btn" tabindex="-1"><svg class="icon"><use href="client/img/icons/icons.svg#calendar"></use></svg></button>
    </span>
</div>
<div class="input-group{{#ifNotEqual searchParams.type 'between'}} hidden{{/ifNotEqual}} additional">
    <input class="main-element form-control input-sm" type="text" name="{{name}}-additional" value="{{searchData.dateValueTo}}" autocomplete="off">
    <span class="input-group-btn">
        <button type="button" class="btn btn-default btn-icon btn-sm date-picker-btn" tabindex="-1"><svg class="icon"><use href="client/img/icons/icons.svg#calendar"></use></svg></button>
    </span>
</div>
<div class="hidden additional-number">
    <input class="main-element form-control input-sm" type="number" name="{{name}}-number" value="{{searchParams.number}}" placeholder ="{{translate 'Number'}}" autocomplete="off">
</div>
