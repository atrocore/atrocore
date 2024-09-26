<div class="panel-default entity-manager-container">
    <div class="panel-body-form">
        <div class="row">
            <div class="cell form-group col-md-6" data-name="type">
                <label class="control-label"
                       data-name="type">{{translate 'type' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="type">
                    {{{type}}}
                </div>
            </div>
        </div>
        <div class="row">
            <div class="cell form-group col-md-6" data-name="name">
                <label class="control-label"
                       data-name="name">{{translate 'name' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="name">
                    {{{name}}}
                </div>
            </div>
        </div>
        <div class="row">
            <div class="cell form-group col-md-6" data-name="labelSingular">
                <label class="control-label"
                       data-name="labelSingular">{{translate 'labelSingular' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="labelSingular">
                    {{{labelSingular}}}
                </div>
            </div>
            <div class="cell form-group col-md-6" data-name="labelPlural">
                <label class="control-label"
                       data-name="labelPlural">{{translate 'labelPlural' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="labelPlural">
                    {{{labelPlural}}}
                </div>
            </div>
        </div>

        <div class="row">
            <div class="cell form-group col-md-6" data-name="disabled">
                <label class="control-label"
                       data-name="disabled">{{translate 'disabled' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="disabled">
                    {{{disabled}}}
                </div>
            </div>
            {{#if auditable}}
            <div class="cell form-group col-md-6" data-name="streamDisabled">
                <label class="control-label" data-name="streamDisabled">{{translate 'streamDisabled' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="streamDisabled">
                    {{{streamDisabled}}}
                </div>
            </div>
            {{/if}}
        </div>

        {{#if sortBy}}
        <div class="row">
            <div class="cell form-group col-md-6" data-name="sortBy">
                <label class="control-label"
                       data-name="sortBy">{{translate 'sortBy' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="sortBy">
                    {{{sortBy}}}
                </div>
            </div>
            <div class="cell form-group col-md-6" data-name="sortDirection">
                <label class="control-label"
                       data-name="sortDirection">{{translate 'sortDirection' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="sortDirection">
                    {{{sortDirection}}}
                </div>
            </div>
        </div>
        {{/if}}

        {{#unless isNew}}
        <div class="row">
            <div class="cell form-group col-md-6" data-name="textFilterFields">
                <label class="control-label"
                       data-name="textFilterFields">{{translate 'textFilterFields' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="textFilterFields">
                    {{{textFilterFields}}}
                </div>
            </div>
            <div class="cell form-group col-md-6" data-name="statusField">
                <label class="control-label"
                       data-name="statusField">{{translate 'statusField' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="statusField">
                    {{{statusField}}}
                </div>
            </div>
        </div>

        <div class="row">
            <div class="cell form-group col-md-6" data-name="kanbanViewMode">
                <label class="control-label"
                       data-name="kanbanViewMode">{{translate 'kanbanViewMode' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="kanbanViewMode">
                    {{{kanbanViewMode}}}
                </div>
            </div>
            <div class="cell form-group col-md-6" data-name="kanbanStatusIgnoreList">
                <label class="control-label"
                       data-name="kanbanStatusIgnoreList">{{translate 'kanbanStatusIgnoreList' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="kanbanStatusIgnoreList">
                    {{{kanbanStatusIgnoreList}}}
                </div>
            </div>
        </div>
        {{/unless}}

        <div class="row">
            <div class="cell form-group col-md-6" data-name="iconClass">
                <label class="control-label"
                       data-name="iconClass">{{translate 'iconClass' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="iconClass">
                    {{{iconClass}}}
                </div>
            </div>
            {{#if color}}
            <div class="cell form-group col-md-6" data-name="color">
                <label class="control-label"
                       data-name="color">{{translate 'color' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="color">
                    {{{color}}}
                </div>
            </div>
            {{/if}}
        </div>
        <div class="row">
            <div class="cell form-group col-md-6" data-name="hasArchive">
                <label class="control-label"
                       data-name="hasArchive">{{translate 'hasArchive' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="hasArchive">
                    {{{hasArchive}}}
                </div>
            </div>
            {{#unless isActiveUnavailable}}
            <div class="cell form-group col-md-6" data-name="hasActive">
                <label class="control-label"
                       data-name="hasArchive">{{translate 'hasActive' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="hasActive">
                    {{{hasActive}}}
                </div>
            </div>
            {{/unless}}
        </div>
        <div class="row">
            <div class="cell form-group col-md-6" data-name="deleteWithoutConfirmation">
                <label class="control-label"
                       data-name="deleteWithoutConfirmation">{{translate 'deleteWithoutConfirmation' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="deleteWithoutConfirmation">
                    {{{deleteWithoutConfirmation}}}
                </div>
            </div>
        </div>
        <div class="row">
            <div class="cell form-group col-md-6" data-name="modifiedExtendedRelations">
                <label class="control-label"
                       data-name="modifiedExtendedRelations">{{translate 'modifiedExtendedRelations' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="modifiedExtendedRelations">
                    {{{modifiedExtendedRelations}}}
                </div>
            </div>
        </div>
        <div class="row">
            <div class="cell form-group col-md-6" data-name="duplicatableRelations">
                <label class="control-label"
                       data-name="duplicatableRelations">{{translate 'duplicatableRelations' category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="duplicatableRelations">
                    {{{duplicatableRelations}}}
                </div>
            </div>
        </div>
    </div>
</div>
{{#each additionalParamsLayout}}
<div class="panel panel-default entity-manager-container entity-manager-{{@key}}">
    <div class="panel-heading"><h4 class="panel-title">{{translate title category='labels' scope='EntityManager'}}</h4></div>
    <div class="panel-body-form">

        <div class="row">
            {{#each fields}}
            {{#each this}}
            <div class="cell form-group col-md-6" data-name="{{this}}">
                <label class="control-label"
                       data-name="{{this}}">{{translate this category='fields' scope='EntityManager'}}</label>
                <div class="field" data-name="{{this}}">
                    {{{var this @root}}}
                </div>
            </div>
            {{/each}}
            {{/each}}
        </div>
    </div>
</div>
{{/each}}