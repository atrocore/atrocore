<script lang="ts">
    import ActionButton from "./ActionButton.svelte";
    import ActionParams from "../interfaces/ActionParams";
    import {UserData} from "../../../../utils/UserData";
    import {Language} from "../../../../utils/Language";

    export let entity: string;
    export let id: string;
    export let followers: Record<string, any>;
    export let onFollow: () => void;
    export let onUnfollow: () => void;

    let loading: boolean = false;
    let isFollowed: boolean;
    let style: string;
    let params: ActionParams;

    $: {
        const userId = UserData.get()?.user.id ?? null;
        isFollowed = userId ? !!followers[userId] : false;
        style = isFollowed ? 'primary' : 'default';
        params = {
            name: 'following',
            action: isFollowed ? 'unfollow' : 'follow',
            html: '<span class="fas fa-bell"></span>',
            style: style,
            disabled: loading,
            tooltip: Language.translate(isFollowed ? 'actionUnfollow' : 'actionFollow'),
        } as ActionParams;
    }

    async function unfollowRecord(): Promise<void> {
        const userData = UserData.get();
        if (!userData) {
            return;
        }

        try {
            loading = true;
            const response = await fetch(`/api/v1/${entity}/${id}/subscription`, {
                method: 'DELETE',
                headers: {
                    'Authorization-Token': btoa(userData.user.userName + ':' + userData.token),
                },
            });

            if (response.ok) {
                delete followers[userData.user.id];
                onUnfollow();
            } else {
                console.error('Error on unfollowing record', response);
            }
        } catch (e) {
            console.error('Error on unfollowing record', e);
        } finally {
            loading = false;
        }
    }

    async function followRecord(): Promise<void> {
        const userData = UserData.get();
        if (!userData) {
            return;
        }

        try {
            const response = await fetch(`/api/v1/${entity}/${id}/subscription`, {
                method: 'PUT',
                headers: {
                    'Authorization-Token': btoa(userData.user.userName + ':' + userData.token),
                },
            });

            if (response.ok) {
                followers[userData.user.id] = userData.user.name;
                onUnfollow();
            } else {
                console.error('Error on following record', response);
            }
        } catch (e) {
            console.error('Error on following record', e);
        } finally {
            loading = false;
        }

        onFollow();
    }

    function execute(e: CustomEvent): void {
        if (params.action === 'unfollow') {
            unfollowRecord();
        } else {
            followRecord();
        }
    }
</script>

<ActionButton {params} on:execute={execute}/>