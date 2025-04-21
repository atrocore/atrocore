{{#if favoritesList.length }}
    <ul {{#if class}}class="{{class}}"{{/if}}>
        {{#each favoritesList}}
            <li data-name="{{name}}">
                <a href="{{link}}" class="favorite nav-link{{#ifEqual ../activeTab name}} active{{/ifEqual}}"{{#if color}} style="border-color: {{color}}"{{/if}} title="{{label}}">
                    <span class="label-wrapper">
                        {{#if ../hasIcons}}
                            {{#if defaultIconSrc}}
                                <img src="{{defaultIconSrc}}" class="favorite-icon default-icon" {{#if colorFilter}} style="{{{colorFilter}}}"{{/if}}>
                            {{else}}
                                 <img src="{{iconSrc}}" class="favorite-icon icon" {{#if colorFilter}} style="{{{colorFilter}}}"{{/if}}>
                            {{/if}}
                        {{/if}}
                        <span class="full-label">{{label}}</span>
                    </span>
                </a>
            </li>
        {{/each}}
    </ul>
    {{#if hasArrow}}<a class="favorite favorite-arrow" type="button" href="javascript:" data-action="openFavoritesDropdown" style="padding: 5px;" ><i class="ph ph-arrow-right"></i></a>{{/if}}
{{else}}{{#if showEmptyPlaceholder}}{{translate 'noData'}}{{/if}}{{/if}}