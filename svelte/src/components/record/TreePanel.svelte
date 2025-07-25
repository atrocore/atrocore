<script lang="ts">
    import {onMount, tick} from 'svelte';
    import {fade} from 'svelte/transition';
    import {Storage} from "../../utils/Storage";
    import {LayoutManager} from "../../utils/LayoutManager";
    import {Language} from "../../utils/Language";
    import {Metadata} from "../../utils/Metadata";
    import {Config} from "../../utils/Config.js";
    import {Notifier} from "../../utils/Notifier";
    import {UserData} from "../../utils/UserData";
    import Preloader from "../icons/loading/Preloader.svelte";
    import BaseSidebar from "./BaseSidebar.svelte";

    export let scope: string;
    export let model;
    export let collection;
    export let callbacks: object;
    export let minWidth: number = 300;
    export let maxWidth: number = 600;
    export let currentWidth: number = minWidth;
    export let isCollapsed: boolean = false;
    export let mode: string;
    export let maxSize: number = Config.get('recordsPerPageSmall') || 20;

    export let renderLayoutEditor;

    export let isAdminPage: boolean = false;

    let isPinned: boolean = true;
    let treeElement: HTMLElement;
    let layoutEditorElement: HTMLElement;
    let searchInputElement: HTMLInputElement;
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
    let sortFields = [];
    let applyAdvancedFilter = false;

    $: treeScope = activeItem ? getLinkScope(activeItem.name) : null
    $: isSelectionEnabled = activeItem && (((!['_self', '_bookmark'].includes(activeItem.name)) && mode === 'list') || (activeItem.name === '_admin'))
    $: {
        if (!treeScope) {
            sortFields = []
        } else {
            const sortScope = treeScope === 'Bookmark' ? scope : treeScope
            const fieldDefs = Metadata.get(['entityDefs', sortScope, 'fields']);
            sortFields = Object.keys(fieldDefs).filter(function (item) {
                return ['varchar', 'text', 'int', 'float', 'date', 'datetime'].includes(fieldDefs[item].type) && !fieldDefs[item].notStorable;
            }).sort(function (v1, v2) {
                return Language.translate(v1, 'fields', sortScope).localeCompare(Language.translate(v2, 'fields', sortScope));
            }).map(item => {
                return {name: item, label: Language.translate(item, 'fields', sortScope)}
            })
        }
    }

    export function handleCollectionSearch(searchedCollection) {
        if (collection && searchedCollection.name === scope) {
            Storage.set('treeWhereData', scope, searchedCollection.where)
            Storage.set('useDataRequest', scope, searchedCollection.length < 50 ? 'yes' : 'no')
        }
        if (!isCollapsed) {
            rebuildTree()
        }
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
        if (!['_self', '_bookmark'].includes(activeItem.name) || !applyAdvancedFilter) {
            whereData = [];
        }

        return whereData
    }

    function canUseDataRequest() {
        if (!['_self', '_bookmark'].includes(activeItem.name) || !applyAdvancedFilter) {
            return true
        }
        return  Storage.get('useDataRequest', scope) === 'yes'
    }

    function getHashScope() {
        let locationHash = window.location.hash;
        return locationHash.split('/').shift().replace('#', '');
    }

    function buildTree(data = null): void {
        if (!activeItem) {
            return;
        }

        if (activeItem.name === '_admin') {
            data = getAdminTreeData();
        }
        let $tree = window.$(treeElement);
        let whereData = getWhereData();
        let hasTextFilter = !!searchValue;

        if (
            data === null   && Metadata.get(['scopes', treeScope, 'type']) === 'Hierarchy' &&
            !Metadata.get(['scopes', treeScope, 'hierarchyDisabled']) && ((canUseDataRequest() && whereData.length) || hasTextFilter)
        ) {
            treeLoading = true;
            if(searchValue){
                whereData.push({"type": "textFilter", "value": searchValue});
            }
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
            dataFilter: response => filterResponse(response),
            selectable: true,
            saveState: false,
            autoOpen: false,
            dragAndDrop: Metadata.get(['scopes', treeScope, 'multiParents']) !== true && Metadata.get(['scopes', treeScope, 'dragAndDrop']) && sortBy === 'sortOrder',
            useContextMenu: false,
            closedIcon: window.$('<i class="ph ph-caret-right"></i>'),
            openedIcon: window.$('<i class="ph ph-caret-down"></i>'),
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

                if (activeItem.name === '_admin'
                    && ((Metadata.get(['scopes', getHashScope()])
                        && (node.id.includes('#' + getHashScope()+'/'))
                        && isLinkExistsOnce(getHashScope())) || node.id === window.location.hash)) {
                    $tree.tree('addToSelection', node);
                    $li.addClass('jqtree-selected');
                }

                $title.attr('data-id', node.id);

                if (node.showMoreDirection) {
                    $li.addClass('show-more');
                    $li.addClass('show-more-' + node.showMoreDirection);
                    $li.find('.jqtree-title').addClass('more-label');
                } else {
                    $title.attr('title', node.name);
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
            if (callbacks?.treeLoad) {
                callbacks.treeLoad(treeScope, treeData);
            }
        })
        $tree.on('tree.refresh', e => {
            if (activeItem.name === '_admin') {
                let hashScope = getHashScope();
                if (Metadata.get(['scopes', hashScope])) {
                    selectTreeNode(hashScope, [])
                    if (callbacks?.selectNode) {
                        callbacks.selectNode({id: hashScope}, true);
                    }
                }
                return;
            }

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
                _sortAsc: sortAsc,
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

            if (activeItem.name === '_admin' && !e.node.disabled) {
                window.location.href = e.node.id;
                return;
            }

            if (e.node.disabled) {
                return false;
            }

            const el = window.$(e.click_event.target);
            if (el.hasClass('jqtree-title') || el.parent().hasClass('jqtree-title')) {
                let node = e.node;

                if (node.showMoreDirection) {
                    return loadMore(node);
                }

                if (node.element) {
                    appendUnsetButton(window.$(node.element));
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

    function appendUnsetButton($el): void {
        if ($el && $el.length) {
            removeUnsetButton($el);

            if (selectNodeId && isSelectionEnabled) {
                const button = document.createElement('span');
                button.classList.add('reset-button', 'ph', 'ph-x', 'pull-right');
                button.addEventListener('click', () => {
                    removeUnsetButton($el);
                    callUnselectNode();
                });
                $el.append(button);
            }
        }
    }

    function removeUnsetButton($el): void {
        if ($el && $el.length) {
            $el.find('.reset-button').remove();
        }
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
        let whereData = [];
        if(searchValue) {
            whereData.push({"type": "textFilter", "value": searchValue});
        }

        if(['_self','_bookmark'].includes(activeItem.name) ) {
                whereData = [...whereData, ...getWhereData() ]
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
                    removeUnsetButton(li);
                    return;
                } else if (!li.hasClass('jqtree-selected')) {
                    li.addClass('jqtree-selected');
                }

                if (li.hasClass('jqtree-selected')) {
                    appendUnsetButton(li);
                }
            });
        }

        openNodes($tree, ids, onFinished);
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
            if (node.element) {
                removeUnsetButton(window.$(node.element));
            }

            $tree.tree('removeFromSelection', node);
        }
    }

    export function getTreeEl() {
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
        Storage.clear('treeSearchValue', '_admin')

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
        initSorting(false)
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
        if (searchValue && activeItem.name === '_admin') {
            Storage.set('treeSearchValue', '_admin', searchValue)
        } else if (searchValue) {
            Storage.set('treeSearchValue', scope, searchValue)
        } else {
            Storage.clear('treeSearchValue', scope)
            Storage.clear('treeSearchValue', '_admin')
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

    function isLinkExistsOnce(scope: string) {
        let count = 0;
        for (const treeItem of getAdminTreeData()) {
            for (const child of treeItem.children) {
                if(child.id.includes('#'+scope)) {
                    count += 1;
                }
            }
        }

        return count == 1;
    }

    function getAdminTreeData() {
        let data = Metadata.get(['app', 'adminPanel']);
        let total = Object.keys(data).length;
        let result:[] = [];
        let i = 0;
        Object.entries(data).forEach(([k, v]) => {
            let treeItem = {
                id: k,
                name: Language.get('Admin', 'labels', v['label']) ?? v['label'],
                offset: i,
                total: total,
                disabled: true,
                load_on_demand: false,
                children: []
            }
            let j = 0;
            let totalItem = v['itemList'].length;
            for (const item of v['itemList']) {
                if(item.listDisabled) {
                    continue;
                }
                const label = Language.translate(item['label'], 'labels', 'Admin');
                if ((searchValue ?? '').length < 3 || (label.toLowerCase().includes(searchValue.toLowerCase()))) {
                    treeItem.children.push({
                        id: item['url'],
                        name: label,
                        offset: j,
                        total: totalItem,
                        disabled: false,
                        load_on_demand: false,
                    });
                    j++;
                }

            }
            if (treeItem.children.length === 0) {
                return;
            }
            result.push(treeItem);
            i++;
        });

        return result;
    }

    function loadLayout(callback) {
        if (isAdminPage) {
            activeItem = {
                name: '_admin',
                label: Language.get('Global', 'labels', 'Administration')
            }
            treeItems = [activeItem];
            callback()
        } else {
            LayoutManager.get(scope, 'leftSidebar', null, null, (data) => {
                layoutData = data
                treeItems = data.layout.map(item => {
                    const type = Metadata.get(['entityDefs', scope, 'fields', item.name, 'type'])
                    let label = ''
                    if (item.name === '_self') {
                        label = Language.get('Global', 'scopeNamesPlural', scope)
                    } else if (item.name === '_bookmark') {
                        label = Language.get('Global', 'scopeNamesPlural', 'Bookmark')
                    } else if (item.name === '_admin') {
                        label = Language.get('Global', 'labels', 'Administration')
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
                    };
                });

                let treeItem = Storage.get('treeItem', scope);
                // admin should always be the selected one
                if (isAdminPage) {
                    treeItem = '_admin';
                }
                if (treeItems.length > 0) {
                    if (!treeItem || !treeItems.find(ti => ti.name === treeItem)) {
                        Storage.set('treeItem', scope, treeItems[0].name);
                        activeItem = treeItems[0];
                    } else {
                        activeItem = treeItems.find(ti => ti.name === treeItem);
                    }
                    initSorting(true);
                }

                callback();
            })
        }
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

        if (!Metadata.get(['entityDefs', treeScope, 'fields', sortBy])) {
            sortBy = sortFields[0]?.name
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

        isPinned = Storage.get('catalog-tree-panel-pin', scope) !== 'not-pinned';

        let treeApplyAdvanced = Storage.get('treeApplyAdvancedFilter', scope);
        if(treeApplyAdvanced) {
            applyAdvancedFilter = treeApplyAdvanced === 'on';
        }else{
            applyAdvancedFilter = true;
        }


        if(collection) {
            Storage.set('treeWhereData', scope, collection.where)
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
                if (activeItem.name === '_admin') {
                    searchValue = Storage.get('treeSearchValue', '_admin') || null;
                } else {
                    searchValue = Storage.get('treeSearchValue', scope) || null;
                }
                if (searchValue) {
                    searchInputElement.value = searchValue;
                }

                if (!isCollapsed) {
                    buildTree();
                }

                if (renderLayoutEditor) {
                    renderLayoutEditor(layoutEditorElement);
                }
            })
        });
    });

    function onSidebarResize(e: CustomEvent): void {
        Storage.set('panelWidth', scope, currentWidth.toString());

        if (callbacks?.treeWidthChanged) {
            callbacks.treeWidthChanged(currentWidth);
        }
    }

    function onSidebarCollapse(e: CustomEvent): void {
        Storage.set('catalog-tree-panel', scope, isCollapsed ? 'collapsed' : '');

        if (!isCollapsed) {
            rebuildTree();
        }
    }

    function onSidebarPin(e: CustomEvent): void {
        Storage.set('catalog-tree-panel-pin', scope, isPinned ? 'pin' : 'not-pinned');
    }

    function handleFilterToggle(e: MouseEvent): void {
        applyAdvancedFilter = !applyAdvancedFilter;
        Storage.set('treeApplyAdvancedFilter', scope, applyAdvancedFilter ? 'on' : 'off' );
        rebuildTree()
    }
</script>

<BaseSidebar className="catalog-tree-panel" position="left" bind:width={currentWidth} bind:isCollapsed={isCollapsed}
             bind:isPinned={isPinned} {minWidth} {maxWidth} on:sidebar-resize={onSidebarResize}
             on:sidebar-collapse={onSidebarCollapse} on:sidebar-pin={onSidebarPin}>
    <div class="category-panel" class:hidden={isCollapsed}>
        {#if layoutLoading}
            <div class="text-center">
                <Preloader heightPx={12} />
            </div>
        {:else if treeItems.length > 0 }
            <div class="panel-group" style="margin-bottom: 10px; min-height: 26px;">
                <div class="btn-group">
                    {#each treeItems as treeItem}
                        <a href="javascript:" on:click={()=>setActiveItem(treeItem)}
                           class="btn btn-link tree-item" class:active={treeItem.name===activeItem.name}>
                            {treeItem.label}
                        </a>
                    {/each}
                    <span bind:this={layoutEditorElement} class="btn layout-editor-container"></span>
                </div>
            </div>
            {#if activeItem}
                <div class="sidebar-header">
                    <h5>{activeItem.label}</h5>
                </div>

                <div class="panel-group category-search" style="margin-bottom: 20px">
                    <div class="field" data-name="category-search">
                        <input type="text" bind:this={searchInputElement}
                               on:keydown={(e) => e.key === 'Enter' && applySearch()} tabindex="1"
                               class="form-control category-search" class:search-enabled={!!searchValue}
                               placeholder={Language.translate('typeToSearch')}>

                        <div class="button-container">
                            {#if searchValue}
                                <button on:click={treeReset} class="ph ph-x reset-search-in-tree-button"></button>
                            {/if}
                            <button on:click={applySearch} class="search-in-tree-button">
                                <i class="ph ph-magnifying-glass"></i>
                            </button>
                        </div>
                    </div>
                    {#if activeItem.name === "_self" || activeItem.name === "_bookmark"}
                        <div style="margin-top:  20px;">
                             <span class="icons-wrapper" >
                                <span class="toggle" class:active={applyAdvancedFilter}
                                      on:click|stopPropagation|preventDefault={handleFilterToggle}
                                >
                                    {#if applyAdvancedFilter}
                                        <i class="ph-fill ph-toggle-right"></i>
                                    {:else}
                                        <i class="ph-fill ph-toggle-left"></i>
                                    {/if}
                                </span>
                                 {Language.translate('applyMainSearchAndFilter')}
                            </span>
                        </div>
                    {/if}
                    {#if activeItem.name !== '_admin' }
                        <div style="margin-top: 20px;display: flex; justify-content: space-between; flex-wrap: wrap">
                            <div class="btn-group" style="display:flex; align-items: stretch;">
                                <button type="button" class="btn btn-default sort-btn" data-tippy="true"
                                        title={Language.translateOption(sortAsc?'asc':'desc','sortDirection','Entity')}
                                        on:click={onSortAscChange}>
                                    <i class={'ph '+(sortAsc ? 'ph-sort-descending':'ph-sort-ascending')}></i>
                                </button>
                                <select class="form-control" style="max-width: 300px; flex: 1;" bind:value={sortBy}
                                        on:change={onSortByChange}>
                                    {#each sortFields as field }
                                        <option value="{field.name}">
                                            {field.label}
                                        </option>
                                    {/each}
                                </select>
                            </div>
                        </div>
                    {/if}
                </div>

                <div class="panel-group category-tree" bind:this={treeElement}>
                </div>
            {/if}
        {/if}
    </div>
</BaseSidebar>

<style>
    .field[data-name="category-search"] {
        position: relative;
    }

    .field[data-name="category-search"] > input.category-search {
        border: 0;
        border-bottom: 1px solid #e8eced;
        background-color: transparent;
        padding: 8px 36px 8px 12px;
    }

    .field[data-name="category-search"] > input.category-search.search-enabled {
        padding-right: 58px;
    }

    .field[data-name="category-search"] .button-container {
        position: absolute;
        top: 50%;
        right: 12px;
        display: flex;
        gap: 10px;
        align-items: center;
        transform: translateY(-50%);
        padding: 0;
    }

    .field[data-name="category-search"] button {
        border: none;
        background: none;
        padding: 0;
    }

    .field[data-name="category-search"] > input.category-search {
        border-bottom: 1px solid #e8eced;
    }

    .tree-item {
        padding: 4px 20px 4px 0;
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

    .sort-btn + select {
        border-left: 0;
        border-top-right-radius: 3px;
        border-bottom-right-radius: 3px;
    }

    .sort-btn + select,
    .sort-btn + select:focus {
        border-color: #e0e0e0;
    }

    .unset-selection i {
        font-size: 14px;
    }

    .layout-editor-container:empty {
        display: none;
    }

    .layout-editor-container:not(:empty) {
        padding: 4px 0;
        line-height: 0;
    }

    .layout-editor-container:not(:empty):active {
        box-shadow: none;
    }

    :global(ul.jqtree-tree .jqtree-toggler .ph) {
        font-size: 16px;
    }

    :global(ul.jqtree-tree .jqtree-element:not(.btn)) {
        display: -webkit-box;
        line-clamp: 1;
        -webkit-line-clamp: 1;
        text-overflow: ellipsis;
        -webkit-box-orient: vertical;
        overflow-y: clip;
        line-height: normal;
    }

    :global(ul.jqtree-tree li.jqtree_common) {
        position: relative;
    }

    :global(ul.jqtree-tree li.jqtree_common .reset-button) {
        margin-top: 6px;
        position: absolute;
        top: 0;
        right: 0;
        cursor: pointer;
    }

    :global(ul.jqtree-tree .jqtree_common.disabled > div > span) {
        color: #000;
        font-weight: bold;
    }

    .category-panel .icons-wrapper .toggle {
        cursor: pointer;
        margin-right: 3px;
        vertical-align: middle;
    }

    .category-panel .icons-wrapper .toggle.active {
        color: #06c;
    }

    .category-panel .icons-wrapper .toggle i {
        font-size: 24px;
    }
</style>