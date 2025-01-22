<div class="menu">
    <ul class="nav navbar-nav tabs">
        <li>
            <a class="minimizer" href="javascript:">
                <span class="fas fa-angle-right right"></span>
                <span class="fas fa-angle-left left"></span>
            </a>
        </li>
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
    <footer>{{{footer}}}</footer>
</div>