<div class="side-header">
    <div class="btn-group record-buttons" role="group">
        {{#each buttonList}}{{button name scope=../../entityType label=label style=style hidden=hidden html=html}}{{/each}}
    </div>
    <div class="autosave">
        <input type="checkbox" class="main-element" data-name="autosave" id="side-autosave"{{#if autosave}} checked{{/if}}>
        <label for="side-autosave">Autosave</label>
    </div>
</div>
<div class="side-body">
    {{{edit}}}
</div>