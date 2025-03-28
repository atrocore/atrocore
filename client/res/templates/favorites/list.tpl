{{#if favoritesList.length }}
    <ul {{#if class}}class="{{class}}"{{/if}}>
        {{#each favoritesList}}
            <li data-name="{{name}}">
                <a href="{{link}}" class="favorite nav-link{{#ifEqual ../activeTab name}} active{{/ifEqual}}"{{#if color}} style="border-color: {{color}}"{{/if}} title="{{label}}">
                    <span class="label-wrapper">
                        {{#if ../hasIcons}}
                            {{#if iconSrc}}
                                <img src="{{iconSrc}}" class="favorite-icon default-icon">
                            {{else}}
                                <span class="favorite-icon {{iconClass}}"></span>
                            {{/if}}
                        {{/if}}
                        <span class="full-label">{{label}}</span>
                    </span>
                </a>
            </li>
        {{/each}}
    </ul>
{{else}}
    {{#if showEmptyPlaceholder}}
        {{translate 'noData'}}
    {{/if}}
{{/if}}