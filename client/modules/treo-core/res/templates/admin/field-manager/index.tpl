<div class="page-header">
    <h3>
        <div class="header-breadcrumbs">
            <div class="breadcrumbs-wrapper">
                <a href="#Admin">{{translate 'Administration'}}</a>
                <a href="#Admin/entityManager">{{translate 'Entity Manager' scope='Admin'}}</a>
                <span class="subsection">{{translate scope category='scopeNames'}}</span>
                {{translate 'Fields' scope='EntityManager'}}
            </div>
        </div>
        <div class="header-title">{{translate 'Fields' scope='EntityManager'}}</div>
    </h3>
</div>

<div class="row">
    <div id="fields-panel" class="col-sm-6">
        <div id="fields-content">
            {{{content}}}
        </div>
    </div>
</div>
