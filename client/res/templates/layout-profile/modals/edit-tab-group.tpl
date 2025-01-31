<div class="row">
    <div class="cell form-group col-md-6" data-name="name">
        <label class="control-label" data-name="name"><span class="label-text">{{translate 'name' category='fields' scope='Global'}}</span></label>
        <div class="field" data-name="name">
            {{{name}}}
        </div>
    </div>
</div>
<div class="row">
    <div class="cell form-group col-md-6" data-name="iconClass">
        <label class="control-label" data-name="iconClass">{{translate 'iconClass' category='fields' scope='EntityManager'}}</label>
        <div class="field" data-name="iconClass">
            {{{iconClass}}}
        </div>
    </div>
    {{#if color}}
    <div class="cell form-group col-md-6" data-name="color">
        <label class="control-label" data-name="color">{{translate 'color' category='fields' scope='EntityManager'}}</label>
        <div class="field" data-name="color">
            {{{color}}}
        </div>
    </div>
    {{/if}}
</div>