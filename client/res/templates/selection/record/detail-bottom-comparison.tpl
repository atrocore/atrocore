
{{#each panelList}}
 <div class="panel panel-default panel-{{name}}"  data-name="{{name}}">
        <div class="panel-heading">
            <h4 class="panel-title">
              {{label}}
            </h4>
        </div>
        <div class="panel-body">
             {{{var name ../this}}}
        </div>
 </div>
{{/each}}
