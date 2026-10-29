<ul class="list-group array-add-list-group">
{{#each optionList}}
    <li class="list-group-item clearfix">
        {{prop ../translatedOptions this}}
        <button class="btn btn-default pull-right" data-value="{{./this}}" data-action="add">{{translate 'Select'}}</button>
    </li>
{{/each}}
</ul>
