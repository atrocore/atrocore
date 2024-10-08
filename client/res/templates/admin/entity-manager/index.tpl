<div class="page-header">
    <h3>
        <div class="header-breadcrumbs fixed-header-breadcrumbs">
            <div class="breadcrumbs-wrapper">
                <a href="#Admin">{{translate 'Administration'}}</a>{{translate 'Entity Manager' scope='Admin'}}
            </div>
        </div>
        <div class="header-title">{{translate 'Entity Manager' scope='Admin'}}</div>
    </h3>
</div>

<div class="button-container">
    <button class="btn btn-primary" data-action="createEntity">{{translate 'Create Entity' scope='Admin'}}</button>
</div>

<table class="table table-hover">
    <thead>
    <tr>
        <th>{{translate 'name' scope='EntityManager' category='fields'}}</th>
        <th>{{translate 'label' scope='EntityManager' category='fields'}}</th>
        <th>{{translate 'type' scope='EntityManager' category='fields'}}</th>
        <th>&nbsp;</th>
        <th>&nbsp;</th>
        <th>&nbsp;</th>
        <th>&nbsp;</th>
    </tr>
    </thead>
    <tbody>
    {{#each scopeDataList}}
    <tr data-scope="{{name}}">
        <td width="25%">
            {{name}}
        </td>
        <td width="">
            {{label}}
        </td>
        <td width="120">
            {{#if type}}
            {{translateOption type field='type' scope='EntityManager'}}
            {{/if}}
        </td>
        <td width="120">
            {{#if customizable}}
            <a href="#Admin/fieldManager/scope={{name}}">{{translate 'Fields' scope='EntityManager'}}</a>
            {{/if}}
        </td>
        <td width="120">
            {{#if hasRelationships}}
            <a href="#Admin/linkManager/scope={{name}}">{{translate 'Relationships' scope='EntityManager'}}</a>
            {{/if}}
        </td>
        <td align="right" width="120">
            {{#if customizable}}
            <a href="javascript:" data-action="editEntity" data-scope="{{name}}" title="{{translate 'Edit'}}">
                {{translate 'Edit'}}
            </a>
            {{/if}}
        </td>
        <td class="cell" align="right" width="120" data-name="buttons">
            {{#if customizable}}
            {{#if isRemovable}}
            <div class="list-row-buttons btn-group pull-right">
                <button type="button" class="btn btn-link btn-sm dropdown-toggle" data-toggle="dropdown">
                    <span class="caret"></span>
                </button>
                <ul class="dropdown-menu pull-right">
                    <li><a href="javascript:" data-action="removeEntity" data-scope="{{name}}">{{translate 'Remove'}}</a></li>
                </ul>
            </div>
            {{/if}}
            {{/if}}
        </td>
    </tr>
    {{/each}}
    </tbody>
</table>

