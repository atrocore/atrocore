<div class="navbar navbar-inverse" role="navigation">
    <div class="navbar-header">
        <button type="button" class="navbar-toggle" >
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
        </button>
        <a class="navbar-brand nav-link" href="#"><img src="{{logoSrc}}" class="logo"><span class="home-icon fas fa-th-large" title="{{translate 'Home'}}"></span></a>
        <div class="navbar-header-inner pull-right">
            {{#if globalSearch}}
                <button type="button" class="search-toggle pull-left visible-xs">
                    <span class="fa fa-search"></span>
                </button>
            {{/if}}
            <ul class="visible-xs header-right pull-left">
                <li class="notifications-badge-container ">
                    {{{notificationsBadge}}}
                </li>
            </ul>
            <div class="dropdown menu-container visible-xs pull-left">
                <a id="nav-menu-dropdown" class="dropdown-toggle" data-toggle="dropdown" href="#" title="{{translate 'Menu'}}"><span class="fas fa-bars"></span></a>
                <ul class="dropdown-menu" role="menu" aria-labelledby="nav-menu-dropdown">
                    {{#each menuDataList}}
                    {{#unless divider}}
                    <li><a href="{{#if link}}{{link}}{{else}}javascript:{{/if}}" class="nav-link{{#if action}} action{{/if}}"{{#if action}} data-action="{{action}}"{{/if}}>{{#if html}}{{{html}}}{{else}}{{label}}{{/if}}</a></li>
                    {{else}}
                    <li class="divider"></li>
                    {{/unless}}
                    {{/each}}
                </ul>
            </div>

        </div>
    </div>

    <div class="menu">
        <ul class="nav navbar-nav tabs">
            {{#each tabDefsList}}
            {{#unless isInMore}}
            <li data-name="{{name}}" class="not-in-more tab">
                <a href="{{link}}" class="nav-link"{{#if color}} style="border-color: {{color}}"{{/if}}>
                    <span class="full-label">{{label}}</span>
                    <span class="short-label" title="{{label}}"{{#if color}} style="color: {{color}}"{{/if}}>
                        {{#if iconClass}}
                        <span class="{{iconClass}}"></span>
                        {{else}}
                        {{#if colorIconClass}}
                        <span class="{{colorIconClass}}" style="color: {{color}}"></span>
                        {{/if}}
                        <span class="short-label-text">{{shortLabel}}</span>
                        {{/if}}
                    </span>
                </a>
            </li>
            {{/unless}}
            {{/each}}
            {{#if isMoreFields}}
            <li class="dropdown more">
                <a id="nav-more-tabs-dropdown" class="dropdown-toggle" data-toggle="dropdown" href="#"><span class="fas fa-ellipsis-h"></span></a>
                <ul class="dropdown-menu" role="menu" aria-labelledby="nav-more-tabs-dropdown">
                    {{#each tabDefsList}}
                    {{#if isInMore}}
                        <li data-name="{{name}}" class="in-more tab">
                            <a href="{{link}}" class="nav-link"{{#if color}} style="border-color: {{color}}"{{/if}}>
                                <span class="short-label"{{#if color}} style="color: {{color}}"{{/if}}>
                                    {{#if iconClass}}
                                    <span class="{{iconClass}}"></span>
                                    {{else}}
                                    {{#if colorIconClass}}
                                    <span class="{{colorIconClass}}" style="color: {{color}}"></span>
                                    {{/if}}
                                    <span class="short-label-text">&nbsp;</span>
                                    {{/if}}
                                </span>
                                <span class="full-label">{{label}}</span>
                            </a>
                        </li>
                    {{/if}}
                    {{/each}}
                </ul>
            </li>
            {{/if}}
        </ul>
    </div>

    <div class="collapse navbar-collapse navbar-body">

        <ul class="nav navbar-nav navbar-right">
            <li class="dropdown menu-container hidden-xs">
                <a id="nav-menu-dropdown" class="dropdown-toggle" data-toggle="dropdown" href="#" title="{{translate 'Menu'}}"><span class="fas fa-bars"></span></a>
                <ul class="dropdown-menu" role="menu" aria-labelledby="nav-menu-dropdown">
                    {{#each menuDataList}}
                    {{#unless divider}}
                    <li><a href="{{#if link}}{{link}}{{else}}javascript:{{/if}}" class="nav-link{{#if action}} action{{/if}}"{{#if action}} data-action="{{action}}"{{/if}}>{{#if html}}{{{html}}}{{else}}{{label}}{{/if}}</a></li>
                    {{else}}
                    <li class="divider"></li>
                    {{/unless}}
                    {{/each}}
                </ul>
            </li>
            {{#if lastViewed}}
            <li class="nav navbar-nav">
                <a href="#LastViewed" class="nav-link action" data-action="showLastViewed">
                    <span class="fas fa-history"></span>
                </a>
            </li>
            {{/if}}
            {{#if enableQuickCreate}}
            <li class="dropdown hidden-xs quick-create-container hidden-xs">
	            <a id="nav-quick-create-dropdown" class="dropdown-toggle" data-toggle="dropdown" href="#" title="{{translate 'Create'}}"><i class="fas fa-plus"></i></a>
                <ul class="dropdown-menu" role="menu" aria-labelledby="nav-quick-create-dropdown">
                    <li class="dropdown-header">{{translate 'quickCreate'}}</li>
                    {{#each quickCreateList}}
                    <li><a href="#{{./this}}/create" data-name="{{./this}}" data-action="quick-create">{{translate this category='scopeNames'}}</a></li>
                    {{/each}}
                </ul>
            </li>
            {{/if}}
            <li class="dropdown notifications-badge-container hidden-xs">
                {{{notificationsBadge}}}
            </li>
            {{#if globalSearch}}
            <li class="nav navbar-nav navbar-form global-search-container">
                {{{globalSearch}}}
            </li>
            {{/if}}
        </ul>
        <a class="minimizer" href="javascript:">
            <span class="fas fa-chevron-right right"></span>
            <span class="fas fa-chevron-left left"></span>
        </a>
    </div>
</div>
