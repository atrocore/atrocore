<select class="form-control search-type input-sm" name="{{name}}-type">
    {{options searchTypeList searchType field='searchRanges'}}
</select>
<div class="primary">
	<div class="input-group">
	    <input class="form-control input-sm" type="text" name="{{nameName}}" value="{{searchData.nameValue}}" autocomplete="off" placeholder="{{translate 'Select'}}">
	    <span class="input-group-btn">
	        <button type="button" class="btn btn-sm btn-default btn-icon" data-action="selectLink" tabindex="-1" title="{{translate 'Select'}}"><i class="fas fa-angle-up"></i></button>
	        <button type="button" class="btn btn-sm btn-default btn-icon" data-action="clearLink" tabindex="-1"><i class="fas fa-times"></i></button>
	    </span>
	</div>
	<input type="hidden" name="{{idName}}" value="{{searchData.idValue}}">
</div>

<div class="one-of-container hidden">
    <div class="link-one-of-container link-container list-group">
    </div>

    <div class="input-group add-team">
        <input class="form-control input-sm element-one-of" type="text" name="" value="" autocomplete="off" placeholder="{{translate 'Select'}}">
        <span class="input-group-btn">
            <button data-action="selectLinkOneOf" class="btn btn-default btn-sm btn-icon" type="button" tabindex="-1" title="{{translate 'Select'}}"><span class="fas fa-angle-up"></span></button>
        </span>
    </div>
</div>

<div class="teams-container hidden">
    <div class="link-teams-container link-container list-group">
    </div>
    <div class="input-group add-team">
        <input class="form-control input-sm element-teams" type="text" name="" value="" autocomplete="off" placeholder="{{translate 'Select'}}">
        <span class="input-group-btn">
            <button data-action="selectLinkTeams" class="btn btn-default btn-sm btn-icon" type="button" tabindex="-1" title="{{translate 'Select'}}"><span class="fas fa-angle-up"></span></button>
        </span>
    </div>
</div>
