<div class="page-header">
    <h3>
        <div class="header-breadcrumbs">
            <div class="breadcrumbs-wrapper">
                <a href="#Admin">{{translate 'Administration'}}</a>
                <a href="#Admin/entityManager">{{translate 'Entity Manager' scope='Admin'}}</a>
                <span class="subsection">{{translate scope category='scopeNames'}}</span>
                {{translate 'Relationships' scope='EntityManager'}}
            </div>
        </div>
        <div class="header-title">{{translate 'Relationships' scope='EntityManager'}}</div>
    </h3>
</div>

<div class="button-container">
    <button class="btn btn-primary" data-action="createLink">{{translate 'Create Link' scope='Admin'}}</button>
</div>


<table class="table">
    {{#unless linkDataList.length}}
    <tr>
        <td>
            {{translate 'No Data'}}
        </td>
    </tr>
    {{/unless}}
    {{#each linkDataList}}
    <tr data-link="{{link}}">
        <td width="15%" align="left">
            {{translate entity category='scopeNames'}}
        </td>
        <td width="15%" align="left">
            {{linkForeign}}
        </td>
        <td width="10%" align="center">
            <strong>
                {{translateOption type field='linkType' scope='EntityManager'}}
            </strong>
        </td>
        <td width="15%" align="right">
            {{link}}
        </td>
        <td width="15%" align="right">
            {{translate entityForeign category='scopeNames'}}
        </td>
        <td align="right" width="10%">
            <a href="javascript:" data-action="editLink" data-link="{{link}}">
                {{translate 'Edit'}}
            </a>
        </td>
        <td align="right" width="10%">
            {{#if isCustom}}
            <a href="javascript:" data-action="removeLink" data-link="{{link}}">
                {{translate 'Remove'}}
            </a>
            {{/if}}
        </td>
    </tr>
    {{/each}}
</table>


