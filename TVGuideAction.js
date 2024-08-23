import Dialog from "../../Library/Dialog.js";
import Dropdown from "../../Library/Dropdown.js";
import Confirm from "../../Library/Confirm.js";

export default class TVGuideAction
{
    #app;

    constructor(app)
    {
        this.#app = app;
    }

    async channelGroupDropdown(e, target)
    {
        let dropdown = new Dropdown(e, target, {
            closeOnClickInside: false,
        });

        if (!Dropdown.isOpen()) {
            return;
        }

        let template = document.getElementById('channel-group-dropdown-content').cloneNode(true);
        for (const elm of template.content.querySelectorAll('a, button')) {
            elm.addEventListener('click', () => {
                Dropdown.close();
            });
        }

        dropdown.setContent(template);
    }

    async programInfoModal(e, target)
    {
        e.preventDefault();

        let modal = new Dialog(target.textContent, this.#app.loadingElm);

        let xhr;
        try {
            xhr = await this.#app.ajax.post('/modal/tv-guide/program-info', {
                program_id: target.dataset.programId
            });
        } catch (e) {
            modal.close();
            throw e;
        }

        modal.setHtmlContent(xhr.response);

        let closeButton = document.createElement('button');
        closeButton.classList.add('close-button');
        closeButton.textContent = _('Close');
        closeButton.addEventListener('click', () => {
            modal.close();
        });

        modal.element().append(closeButton);

        let program = target.closest('.epg-program.running');

        if (program) {
            let modalProgress = document.querySelector('dialog .progress .elapsed');
            modalProgress.style.width = program.dataset.progress + '%';
        }
    }

    async createChannelGroup(e, target)
    {
        let modal = new Dialog(_('Create a channel group'), this.#app.loadingElm, {
            closeConfirm: true,
            closeConfirmText: _('Forget changes?'),
        });

        let xhr;
        try {
            xhr = await this.#app.ajax.post('/modal/tv-guide/channel-group/create');
        } catch (e) {
            modal.close();
            throw e;
        }

        modal.setHtmlContent(xhr.response);
    }

    async editChannelGroup(e, target)
    {
        let closeConfirmText =  _('Forget changes?')
        let modal = new Dialog(_('Edit channel group'), this.#app.loadingElm, {
            closeConfirm: true,
            closeConfirmText: closeConfirmText,
        });

        let xhr;
        try {
            xhr = await this.#app.ajax.post('/modal/tv-guide/channel-group/edit', {
                id: target.dataset.groupId
            });
        } catch (e) {
            modal.close();
            throw e;
        }

        modal.setHtmlContent(xhr.response);
    }

    async deleteChannelGroup(e, target)
    {
        if (!await (new Confirm(_('Delete this channel group?'))).response()) {
            return;
        }

        Dialog.closeParent(target);
        let xhr = await this.#app.ajax.post('/api/tv-guide/channel-group/delete', {
            'id': target.dataset.groupId
        });

        this.#app.toast.success(xhr.response);
        document.getElementById('channel-group-dropdown-content').content
            .querySelector('[data-group-id="' + target.dataset.groupId + '"]')?.remove();
    }

    async toggleUpcomingListVisibility(e, target)
    {
        let broadcastList = target.nextElementSibling;
        let numberOf = target.textContent.match(/\d+/)[0] ?? 0;

        if (broadcastList.classList.contains('hidden')) {
            broadcastList.classList.remove('hidden');
            target.textContent = _('Hide upcoming broadcasts') + ' (' + numberOf + ')';
        } else {
            broadcastList.classList.add('hidden');
            target.textContent = _('Show upcoming broadcasts') + ' (' + numberOf + ')';
        }
    }
}