<script lang="ts">
    import {createEventDispatcher, onMount, tick} from 'svelte';
    import {fade} from 'svelte/transition';
    import {Storage} from "../../utils/Storage";
    import {LayoutManager} from "../../utils/LayoutManager";
    import {Language} from "../../utils/Language";
    import {Metadata} from "../../utils/Metadata";
    import {Config} from "../../utils/Config.js";
    import {Notifier} from "../../utils/Notifier";
    import {UserData} from "../../utils/UserData";

    export let scope: string;
    export let model;
    export let collection;
    export let callbacks: object;
    export let minWidth: number = 220;
    export let maxWidth: number = 600;
    export let currentWidth: number = minWidth;
    export let isCollapsed: boolean = false;
    export let mode;
    export let maxSize: number = Config.get('recordsPerPageSmall') || 20;

    export let renderLayoutEditor;

    const dispatch = createEventDispatcher();

    let treeElement: HTMLElement;
    let layoutEditorElement: HTMLElement;
    let searchInputElement: HTMLInputElement;
    let isDragging: boolean = false;
    let startX: number;
    let startWidth: number;
    let treeItems: [] = [];
    let activeItem: object;
    let layoutLoading = false;
    let treeLoading = false;
    let searchValue = '';
    let treeScope;
    let layoutData;
    let selectNodeId;
    let isHidden = false;
    let sortAsc = true;
    let sortBy = null;
    let sortFields = []

    $: if (currentWidth) {
        if (callbacks?.treeWidthChanged) {
            callbacks.treeWidthChanged(currentWidth);
        }
    }

    $: treePanelWidth = isCollapsed ? 'auto' : `${currentWidth}px`;
    $: treeScope = activeItem ? getLinkScope(activeItem.name) : null
    $: isSelectionEnabled = activeItem && (!['_self', '_bookmark'].includes(activeItem.name)) && mode === 'list'
    $: {
        if (!treeScope) {
            sortFields = []
        } else {
            const fieldDefs = Metadata.get(['entityDefs', treeScope === 'Bookmark' ? scope : treeScope, 'fields']);
            sortFields = Object.keys(fieldDefs).filter(function (item) {
                return ['varchar', 'text', 'int', 'float', 'date', 'datetime'].includes(fieldDefs[item].type) && !fieldDefs[item].notStorable;
            }).sort(function (v1, v2) {
                return Language.translate(v1, 'fields', scope).localeCompare(Language.translate(v2, 'fields', scope));
            })
        }
    }

    export function handleCollectionSearch(searchedCollection) {
        if (collection && searchedCollection.name === scope) {
            Storage.set('treeWhereData', scope, searchedCollection.where)
        }
        if (!isCollapsed) {
            rebuildTree()
        }
    }

    function handleCollapsePanel() {
        isCollapsed = !isCollapsed;

        if (isCollapsed) {
            window.$('.page-header').addClass('collapsed').removeClass('not-collapsed');
            window.$('#tree-list-table').addClass('collapsed');
        } else {
            window.$('.page-header').removeClass('collapsed').addClass('not-collapsed');
            window.$('#tree-list-table').removeClass('collapsed');
        }
        // dispatch('collapse-panel', {isCollapsed});
        Storage.set('catalog-tree-panel', scope, isCollapsed ? 'collapsed' : '');

        if (!isCollapsed) {
            rebuildTree()
        }
    }

    function handleResize(e: MouseEvent) {
        if (!isDragging) return;
        e.preventDefault();

        const width = startWidth + (e.pageX - startX);
        if (width >= minWidth && width <= maxWidth) {
            currentWidth = width;
        }
    }

    function startResize(e: MouseEvent) {
        isDragging = true;
        startX = e.pageX;
        startWidth = currentWidth;

        // Add the event listeners to document instead of window
        document.addEventListener('mousemove', handleResize);
        document.addEventListener('mouseup', stopResize, {once: true});

        // Prevent text selection during drag
        document.body.style.userSelect = 'none';
    }

    function stopResize() {
        if (!isDragging) return;

        isDragging = false;
        Storage.set('panelWidth', scope, currentWidth.toString());

        // Remove event listeners
        document.removeEventListener('mousemove', handleResize);

        // Restore text selection
        document.body.style.userSelect = '';
    }

    //region Tree methods
    export function rebuildTree() {
        destroyTree();
        buildTree();
    }

    function destroyTree() {
        let tree = window.$(treeElement);
        if (tree) {
            tree.tree('destroy');
        }
    }

    function getWhereData(): [] {
        let whereData = Storage.get('treeWhereData', scope) || [];
        if (!['_self', '_bookmark'].includes(activeItem.name)) {
            whereData = []
        }
        return whereData
    }

    function buildTree(data = null): void {
        if (!activeItem) {
            return;
        }


        let $tree = window.$(treeElement);
        let whereData = getWhereData()

        if (data === null && searchValue &&
            Metadata.get(['scopes', treeScope, 'type']) === 'Hierarchy' &&
            !Metadata.get(['scopes', treeScope, 'hierarchyDisabled'])) {
            treeLoading = true
            whereData.push({"type": "textFilter", "value": searchValue})
            Espo.ajax.getRequest(`${treeScope}/action/TreeData`, {
                "where": whereData,
                "scope": scope,
                "link": activeItem.name,
                "sortBy": sortBy,
                "asc": !!sortAsc
            }).then(response => {
                buildTree(response.tree);
            });
            return;
        }

        let treeData = {
            dataUrl: generateUrl(),
            dataFilter: function (response) {
                return filterResponse(response);
            }.bind(this),
            selectable: true,
            saveState: false,
            autoOpen: false,
            dragAndDrop: Metadata.get(['scopes', treeScope, 'multiParents']) !== true && Metadata.get(['scopes', treeScope, 'dragAndDrop']) && sortBy === 'sortOrder',
            useContextMenu: false,
            closedIcon: window.$('<i class="fa fa-angle-right"></i>'),
            openedIcon: window.$('<i class="fa fa-angle-down"></i>'),
            onCreateLi: function (node, $li, is_selected) {
                if (node.disabled) {
                    $li.addClass('disabled');
                } else {
                    $li.removeClass('disabled');
                }

                const $title = $li.find('.jqtree-title');

                /**
                 * Mark search str
                 */
                if (searchValue) {
                    let search = searchValue.replace(/\*/g, '');
                    if (search.length > 0) {
                        let name = $title.html();
                        let matches = name.match(new RegExp(search, 'ig'));
                        if (matches) {
                            let processed = [];
                            matches.forEach(v => {
                                if (!processed.includes(v)) {
                                    processed.push(v);
                                    $title.html(name.replace(new RegExp(v, 'g'), `<b>${v}</b>`));
                                }
                            });
                        }
                    }
                }

                if (['_self', '_bookmark'].includes(activeItem.name) && model && model.get('id') === node.id) {
                    $tree.tree('addToSelection', node);
                    $li.addClass('jqtree-selected');
                }
                if (!['_self', '_bookmark'].includes(activeItem.name) && selectNodeId === node.id) {
                    $tree.tree('addToSelection', node);
                    $li.addClass('jqtree-selected');
                }

                $title.attr('data-id', node.id);

                if (treeData.dragAndDrop && !node.showMoreDirection) {
                    $title.attr('title', Language.translate("useDragAndDrop"));
                }

                if (node.showMoreDirection) {
                    $li.addClass('show-more');
                    $li.addClass('show-more-' + node.showMoreDirection);
                    $li.find('.jqtree-title').addClass('more-label');
                }
            }.bind(this)
        };

        if (data) {
            treeData['data'] = data;
            treeData['autoOpen'] = true;
            treeData['dragAndDrop'] = false;

            delete treeData['dataUrl'];
            delete treeData['dataFilter'];
        }

        $tree.tree(treeData);
        $tree.on('tree.load_data', e => {
            Notifier.notify(false)
        })
        $tree.on('tree.refresh', e => {
            if (Storage.get('selectedNodeId', scope) && mode === 'list') {
                const id = Storage.get('selectedNodeId', scope);
                const route = Storage.get('selectedNodeRoute', scope);
                if (callbacks?.selectNode) {
                    callbacks.selectNode({id, route}, true);
                }
            }
            if (mode === 'detail') {
                if (model && ['_self', '_bookmark'].includes(activeItem.name)) {
                    selectTreeNode(model.get('id'), Object.keys(model.get('hierarchyRoute') ?? []).reverse())
                } else if (Storage.get('selectedNodeId', scope)) {
                    selectTreeNode(Storage.get('selectedNodeId', scope), parseRoute(Storage.get('selectedNodeRoute', scope)))
                }
            }

        })
        $tree.on('tree.move', e => {
            e.preventDefault();

            const parentName = 'parent';

            let moveInfo = e.move_info;
            let data = {
                _position: moveInfo.position,
                _target: moveInfo.target_node.id
            };

            data[parentName + 'Id'] = null;
            data[parentName + 'Name'] = null;

            if (moveInfo.position === 'inside') {
                data[parentName + 'Id'] = moveInfo.target_node.id;
                data[parentName + 'Name'] = moveInfo.target_node.name;
            } else if (moveInfo.target_node.parent.id) {
                data[parentName + 'Id'] = moveInfo.target_node.parent.id
                data[parentName + 'Name'] = moveInfo.target_node.parent.name;
            }

            Espo.ajax.patchRequest(`${treeScope}/${moveInfo.moved_node.id}`, data).success(response => {
                moveInfo.do_move();
            });
        })
        $tree.on('tree.click', e => {
            e.preventDefault();
            if (e.node.disabled) {
                return false;
            }

            const el = window.$(e.click_event.target);
            if (el.hasClass('jqtree-title') || el.parent().hasClass('jqtree-title')) {
                let node = e.node;

                if (node.showMoreDirection) {
                    return loadMore(node);
                }

                let route = [];
                while (node.parent.id) {
                    route.push(node.parent.id);
                    node = node.parent;
                }

                let data = {id: e.node.id, route: '', click: true};
                if (route.length > 0) {
                    data['route'] = "|" + route.reverse().join('|') + "|";
                }

                if (callbacks?.selectNode) {
                    callbacks.selectNode(data);
                }
            }
        });
    }

    function parseRoute(routeStr) {
        let route = [];
        (routeStr || '').split('|').forEach(item => {
            if (item) {
                route.push(item);
            }
        });

        return route;
    }

    function loadMore(node) {
        Notifier.notify('Loading...')
        Espo.ajax.getRequest(generateUrl(node)).then(response => {
            if (response['list']) {
                const $tree = window.$(treeElement);
                if (node.showMoreDirection === 'up') {
                    // prepend
                    filterResponse(JSON.parse(JSON.stringify(response)), 'up').reverse().forEach(item => {
                        prependNode($tree, item, node.getParent());
                    });
                } else if (node.showMoreDirection === 'down') {
                    // append
                    filterResponse(JSON.parse(JSON.stringify(response)), 'down').forEach(item => {
                        appendNode($tree, item, node.getParent());
                    });
                }
                $tree.tree('removeNode', node);
            }
        });
    }

    function appendNode($tree, item, parent) {
        let element = parent || $tree.tree('getTree'),
            nodes = (element.children || []);

        if (nodes.findIndex(node => item.id === node.id) === -1) {
            $tree.tree('appendNode', item, parent);
        }
    }

    function prependNode($tree, item, parent) {
        let element = parent || $tree.tree('getTree'),
            nodes = (element.children || []);

        if (nodes.findIndex(node => item.id === node.id) === -1) {
            $tree.tree('prependNode', item, parent);
        }
    }

    function generateUrl(node) {
        let url = treeScope + `/action/Tree?isTreePanel=1&scope=${scope}&link=${activeItem.name}`;
        if (sortBy) {
            url += `&sortBy=${sortBy}&asc=${sortAsc ? 'true' : 'false'}`
        }
        if (node && node.showMoreDirection) {
            let offset = node.offset;
            let maxSize1 = maxSize;
            if (node.showMoreDirection === 'up') {
                let diff = node.offset - maxSize1;
                offset = node.offset - maxSize1;
                if (diff < 0) {
                    offset = 0;
                    maxSize1 = maxSize1 + diff;
                } else {
                    offset = diff;
                }
            } else if (node.showMoreDirection === 'down') {
                offset = offset + 1;
            }
            url += '&offset=' + offset + '&maxSize=' + maxSize1;
            if (node.getParent()) {
                url += '&node=' + node.getParent().id;
            }
        } else if (node && node.id) {
            url += '&node=' + node.id + '&offset=0&maxSize=' + maxSize;
        } else if (model && model.id && [model.urlRoot, 'Bookmark'].includes(treeScope)) {
            url += '&selectedId=' + model.id;
        }

        let whereData = getWhereData();
        if (searchValue) {
            whereData = [...whereData, {"type": "textFilter", "value": searchValue}]
        }
        if (whereData.length > 0) {
            url += "&";
            url += window.$.param({"where": whereData});
        }
        return url;
    }

    export function selectTreeNode(id, ids) {
        const $tree = window.$(treeElement);
        const onFinished = () => {
            let node = $tree.tree('getNodeById', id);
            if (node) {
                $tree.tree('addToSelection', node);
                selectNodeId = id
            }

            $tree.find(`.jqtree-title`).each((k, el) => {
                el = window.$(el);
                let li = el.parent().parent();

                if (el.data('id') !== id && $tree.tree('getNodeById', el.data('id'))) {
                    $tree.tree('removeFromSelection', $tree.tree('getNodeById', el.data('id')));
                    li.removeClass('jqtree-selected');
                } else if (!li.hasClass('jqtree-selected')) {
                    li.addClass('jqtree-selected');
                }
            });
        }

        openNodes($tree, ids, onFinished)

    }

    function callUnselectNode() {
        if (callbacks?.selectNode) {
            callbacks.selectNode({id: selectNodeId})
        }
    }

    export function unSelectTreeNode(id) {
        const $tree = getTreeEl();
        const node = $tree.tree('getNodeById', id);
        selectNodeId = null;

        if (node) {
            $tree.tree('removeFromSelection', node);
        }
    }

    function getTreeEl() {
        return window.$(treeElement)
    }

    function filterResponse(response, direction = null) {
        if (!response.list) {
            return response;
        }

        pushShowMore(response.list, direction);

        return response.list;
    }

    function pushShowMore(list, direction) {
        if (!direction || direction === 'up') {
            let first = JSON.parse(JSON.stringify(list)).shift();
            if (first && first.offset && first.offset !== 0) {
                list.unshift({
                    id: 'show-more-' + first.offset,
                    offset: first.offset,
                    showMoreDirection: 'up',
                    name: Language.translate('Show more')
                });
            }
        }

        if (!direction || direction === 'down') {
            let last = JSON.parse(JSON.stringify(list)).pop();
            if (last && last.offset && last.total - 1 !== last.offset) {
                list.push({
                    id: 'show-more-' + last.offset,
                    offset: last.offset,
                    showMoreDirection: 'down',
                    name: Language.translate('Show more')
                });
            }
        }

        list.forEach(item => {
            if (item.children) {
                pushShowMore(item.children);
            }
        });
    }

    function openNodes($tree, ids, onFinished) {
        if (!Array.isArray(ids) || ids.length === 0) {
            onFinished()
            return
        }

        const item = ids[0]
        let els = $tree.find(`.jqtree-title[data-id="${item}"]`);
        if (els.length > 0) {
            els.each((k, el) => {
                el = window.$(el);
                let $li = el.parent().parent();
                if ($li.hasClass('jqtree-closed')) {
                    let node = $tree.tree('getNodeByHtmlElement', el);
                    $tree.tree('openNode', node, false, () => openNodes($tree, ids.slice(1), onFinished));
                } else {
                    openNodes($tree, ids.slice(1), onFinished)
                }
            });
        }
    }

    //endregion Tree methods

    function getLinkScope(link): string | null {
        if (link === '_self') {
            return scope
        }
        if (link === '_bookmark') {
            return 'Bookmark'
        }
        return Metadata.get(['entityDefs', scope, 'links', link, 'entity']) ||
            Metadata.get(['entityDefs', scope, 'fields', link, 'entity'])
    }

    function setActiveItem(treeItem) {
        if (activeItem && activeItem.name === treeItem.name) {
            return
        }
        searchValue = ''
        searchInputElement.value = ''
        Storage.clear('treeSearchValue', treeScope)

        if (mode === 'list') {
            if (selectNodeId) {
                if (callbacks?.selectNode) {
                    callbacks.selectNode({id: selectNodeId});
                }
                selectNodeId = null
            }
        } else {
            selectNodeId = null
        }

        activeItem = treeItem
        initSorting()
        Storage.set('treeItem', scope, treeItem.name)
        Notifier.notify('Loading...')
        tick().then(() => {
            rebuildTree()
        })
    }

    function treeReset() {
        searchInputElement.value = ''
        if (mode === 'detail') {
            Storage.clear('treeWhereData', scope)
        }
        applySearch()
    }

    function applySearch() {
        searchValue = searchInputElement.value
        if (searchValue) {
            Storage.set('treeSearchValue', scope, searchValue)
        } else {
            Storage.clear('treeSearchValue', scope)
        }
        Notifier.notify('Loading...')
        rebuildTree()
    }

    export function reloadBookmarks() {
        if (treeScope === 'Bookmark') {
            rebuildTree()
        }
    }

    export function getLayoutData() {
        return layoutData;
    }

    function onSortByChange(event) {
        sortBy = event.target.value
        Storage.set('treeItemSorting', scope, {sortBy, sortAsc})
        rebuildTree()
    }

    function onSortAscChange(event) {
        event.preventDefault();
        sortAsc = !sortAsc;
        Storage.set('treeItemSorting', scope, {sortBy, sortAsc})
        rebuildTree()
    }

    function loadLayout(callback) {
        LayoutManager.get(scope, 'leftSidebar', null, null, (data) => {
            layoutData = data
            treeItems = data.layout.map(item => {
                const type = Metadata.get(['entityDefs', scope, 'fields', item.name, 'type'])
                let label = ''
                if (item.name === '_self') {
                    label = Language.get('Global', 'scopeNamesPlural', scope)
                } else if (item.name === '_bookmark') {
                    label = Language.get('Global', 'scopeNamesPlural', 'Bookmark')
                } else {
                    if (type == 'link') {
                        const itemScope = getLinkScope(item.name)
                        label = Language.get('Global', 'scopeNamesPlural', itemScope)
                    }

                    if (!label) {
                        label = Language.get(scope, 'links', item.name) || Language.get(scope, 'fields', item.name) || Language.get('Global', 'fields', item.name)
                    }
                }


                return {
                    name: item.name,
                    label: label
                }
            })
            const treeItem = Storage.get('treeItem', scope);
            if (treeItems.length > 0) {
                if (!treeItem || !treeItems.find(ti => ti.name === treeItem)) {
                    Storage.set('treeItem', scope, treeItems[0].name);
                    activeItem = treeItems[0]
                } else {
                    activeItem = treeItems.find(ti => ti.name === treeItem);
                }
                initSorting(true)
            }

            callback()
        })
    }

    function initSorting(useCache) {
        treeScope = getLinkScope(activeItem.name)
        if (treeScope === 'Bookmark') {
            treeScope = scope
        }

        if (useCache) {
            if (activeItem.name === Storage.get('treeItem', scope)) {
                const data = Storage.get('treeItemSorting', scope)
                // check if data is valid and field still exist
                if (data && typeof data === 'object' && !!Metadata.get(['entityDefs', treeScope, 'fields', data.sortBy])) {
                    sortAsc = data.sortAsc
                    sortBy = data.sortBy
                    return
                }
            }
        }

        if (Metadata.get(['scopes', treeScope, 'type']) === 'Hierarchy' &&
            !Metadata.get(['scopes', treeScope, 'hierarchyDisabled']) && activeItem.name !== '_bookmark') {
            sortBy = 'sortOrder'
        } else {
            sortBy = Metadata.get(['entityDefs', treeScope, 'collection', 'sortBy']) || 'name'
        }
        sortAsc = !!Metadata.get(['entityDefs', treeScope, 'collection', 'asc'])
        Storage.set('treeItemSorting', scope, {sortBy, sortAsc})
    }

    export function refreshLayout() {
        loadLayout(() => {
            tick().then(() => {
                rebuildTree()
            })
        })
    }

    onMount(() => {
        const savedWidth = Storage.get('panelWidth', scope);
        if (savedWidth) {
            currentWidth = parseInt(savedWidth) || minWidth;
        }

        if (window.innerWidth <= 767 || Storage.get('catalog-tree-panel', scope)) {
            isCollapsed = true;
        }

        loadLayout(() => {
            if (treeItems.length === 0) {
                isCollapsed = true
                if (!UserData.get()?.user?.isAdmin) {
                    // hide panel if user cannot configure
                    isHidden = true
                }
            }
            tick().then(() => {
                searchValue = Storage.get('treeSearchValue', scope) || null;
                if (searchValue) {
                    searchInputElement.value = searchValue
                }

                if (!isCollapsed) {
                    buildTree()
                }
            })
            if (renderLayoutEditor) {
                renderLayoutEditor(layoutEditorElement)
            }
        })

        return () => {
            // Cleanup any remaining event listeners
            if (isDragging) {
                stopResize();
            }
        };
    });
</script>

<aside class="catalog-tree-panel" class:collapsed={isCollapsed} class:catalog-tree-panel-hidden={isCollapsed}
       transition:fade class:hidden={isHidden}
       style="width: {treePanelWidth}">
    <button type="button"
            class="btn btn-link collapse-panel"
            class:collapsed={isCollapsed}
            on:click={handleCollapsePanel}>
        <span class="toggle-icon-left fas fa-angle-left" class:hidden={isCollapsed}></span>
        <span class="toggle-icon-right fas fa-angle-right" class:hidden={!isCollapsed}></span>
    </button>
    <div class="category-panel" class:hidden={isCollapsed}>
        <div style="display: flex;flex-direction: row-reverse;align-items: center;height: 35px">
            <div style="margin-right: 20px" bind:this={layoutEditorElement} class="layout-editor-container"></div>
        </div>

        {#if layoutLoading}
            <div class="text-center">
                <img class="preloader" style="height:12px;" src="client/img/atro-loader.svg">
            </div>
        {:else if treeItems.length > 0 }
            <div class="panel-group" style="margin-bottom: 10px; margin-top: -10px;">
                <div class="btn-group">
                    {#each treeItems as treeItem}
                        <a href="javascript:" on:click={()=>setActiveItem(treeItem)}
                           class="btn btn-link tree-item" class:active={treeItem.name===activeItem.name}>
                            {treeItem.label}
                        </a>
                    {/each}
                </div>
            </div>
            <hr style="margin: 0 -10px">
            {#if activeItem}
                <div class="panel-group category-search" style="margin-bottom: 20px">
                    <h5 style="margin: 20px 0;font-weight: bold; font-size: 16px;">{activeItem.label}</h5>
                    <div class="field" data-name="category-search">
                        <input type="text" bind:this={searchInputElement}
                               on:keydown={(e) => e.key === 'Enter' && applySearch()}
                               class="form-control category-search search-in-tree-input" tabindex="1"
                               placeholder="Type and press Enter...">
                        <button on:click={treeReset} class="fas fa-times reset-search-in-tree-button"
                                class:hidden={!searchValue}></button>
                        <button on:click={applySearch} class="fas fa-search search-in-tree-button"></button>
                    </div>
                    <div style="margin-top: 20px;display: flex; justify-content: space-between;flex-wrap: wrap">
                        <button on:click={callUnselectNode} type="button" style="margin-bottom: 5px"
                                disabled="{!selectNodeId || !isSelectionEnabled}"
                                class="btn btn-default">
                            Unset selection
                        </button>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default sort-btn" data-tippy="true"
                                    title={Language.translateOption(sortAsc?'asc':'desc','sortDirection','Entity')}
                                    on:click={onSortAscChange}>
                                <i class={'fas '+(sortAsc ? 'fa-sort-amount-up':'fa-sort-amount-down')}></i>
                            </button>
                            <select class="form-control" style="width: auto;max-width: 120px"
                                    on:change={onSortByChange} bind:value={sortBy}>
                                {#each sortFields as field }
                                    <option value="{field}">
                                        {Language.translate(field, 'fields', treeScope)}
                                    </option>
                                {/each}
                            </select>
                        </div>
                    </div>
                </div>

                <div class="panel-group category-tree" bind:this={treeElement}>
                </div>
            {/if}
        {/if}


        {#if !isCollapsed}
            <div
                    class="category-panel-resizer"
                    on:mousedown={startResize}
            ></div>
        {/if}
    </div>
</aside>

<style>
    .category-panel-resizer {
        position: absolute;
        right: 0;
        top: 0;
        width: 5px;
        height: 100%;
        cursor: ew-resize;
        background: transparent;
    }

    .category-panel-resizer:hover {
        background: rgba(0, 0, 0, 0.1);
    }

    .tree-item {
        padding: 6px 20px 6px 0;
        color: #333;
        text-decoration: underline;
    }

    .tree-item.active {
        color: #2895ea;
    }

    .tree-item:hover:not(.active) {
        color: #2895ea85;
    }

    .unset-selection {
        background: #dae8fc;
        border: 1px solid;
        border-radius: 5px;
        font-size: 14px;
    }

    .sort-btn:focus {
        background: white;
    }

    .unset-selection i {
        font-size: 14px;
    }
</style>