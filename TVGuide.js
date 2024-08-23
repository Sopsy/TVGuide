import Dropdown from "../Library/Dropdown.js";
import Dialog from "../Library/Dialog.js";

export default class TVGuide
{
    #app;
    #dropdownElm = null;

    constructor(app)
    {
        this.#app = app;

        this.#monitorDateChange();

        if (document.querySelector(".epg-program:not(.ended)")) {
            setInterval(() => {
                this.#updateProgramProgress();
            }, 60 * 1000);
        }

        this.#bindSearchField();

        if (document.querySelector('.epg-channel.single') && !document.querySelector('.epg-channel .no-epg-data') ) {
            this.#programListInfiniteScroll().then();
        }
    }

    async #programListInfiniteScroll()
    {
        const lastProgram = document.querySelector('.epg-channel > :last-child');
        if (lastProgram) {
            let observer = new IntersectionObserver(async (entries) => {
                if (entries[0].isIntersecting) {
                    let channel = document.querySelector('.epg-channel');

                    let xhr = await this.#app.ajax.post('/api/tv-guide/load-programs', {
                        channel: channel.dataset.channelId,
                        start: entries[0].target.dataset.startTime,
                        end: entries[0].target.dataset.endTime
                    });

                    const template = document.createElement('template');
                    template.innerHTML = xhr.response

                    let programCount = template.content.querySelectorAll('.epg-program').length;
                    channel.append(template.content);
                    observer.disconnect();

                    if (programCount !== 0) {
                        observer.observe(document.querySelector('.epg-channel > :last-child'));
                    }
                }
            });
            observer.observe(lastProgram);
        }
    }

    async #programSearchModalListener(e)
    {
        e.preventDefault();

        let target = e.target;

        if (e.type === 'keydown' && e.key !== 'Enter') {
            return;
        }

        let modal = new Dialog(target.textContent, this.#app.loadingElm);

        let xhr;
        try {
            xhr = await this.#app.ajax.post('/modal/tv-guide/program-info', {
                program_id: target.id
            })
        } catch (e) {
            modal.close();
            throw e;
        }

        modal.setHtmlContent(xhr.response);

        document.addEventListener('dialog-close', () => {
            if (target) {
                target.focus();
            }
        });
    }

    #updateProgramProgress( )
    {
        let now = Date.now() * 0.001;

        for (let program of document.querySelectorAll(".epg-program:not(.ended)")) {
            let startTime = parseInt(program.dataset.startTime);
            let endTime = parseInt(program.dataset.endTime);

            if (endTime < now) {
                program.classList.add('ended');
                program.classList.remove('running');
            } else if (startTime < now) {
                if (!program.classList.contains('running')) {
                    program.classList.add('running');
                }

                program.querySelector('.progress progress').value =
                    (((now - startTime) / (endTime - startTime)) * 100).toString();
            }
        }
    }

    #monitorDateChange()
    {
        let dateInput = document.getElementById("date-change-input");

        if (!dateInput) {
            return;
        }

        dateInput.addEventListener('change', (e) => {
            location.href = e.target.value;
        });
    }

    #bindSearchField()
    {
        let searchBox = document.querySelector('#epg-search input');
        if (!searchBox) {
            return;
        }

        let keysPressed = [];

        searchBox.addEventListener('keydown', async (e) => {
            keysPressed.push(e.key);
        });

        searchBox.addEventListener('keyup', async (e) => {

            //make sure there are no other keys pressed
            if (keysPressed.indexOf(e.key) !== -1) {
                keysPressed.splice(keysPressed.indexOf(e.key), 1)
            }

            if (keysPressed.length > 0) {
                keysPressed.length = 0;
                return;
            }

            if (searchBox.value.length < 3) {
                this.#resultsClose();
                return;
            }

            if (this.#dropdownElm) {
                //reduce the number of pointless requests
                if (document.querySelector('p') &&
                    e.key !==
                    'Backspace' &&
                    e.key ===
                    'ArrowUp' ||
                    e.key ===
                    'ArrowRight' ||
                    e.key ===
                    'ArrowLeft') {
                    return;
                }

                if (e.key ===
                    'ArrowDown' &&
                    !this.#dropdownElm.querySelector('p') &&
                    !document.querySelector('.dropdown.search .loading')) {
                    searchBox.blur();
                    document.querySelector('.dropdown.search .result').focus();
                    return;
                } else if (e.key === 'ArrowDown') {
                    return
                }
            }
            await this.#epgSearch(searchBox.value, e);
        });

        searchBox.addEventListener('focus', async (e) => {

            if (searchBox.value.length < 3) {
                this.#resultsClose();
                return;
            }
            await this.#epgSearch(searchBox.value, e);
        });

        // clear-button in the search field
        searchBox.addEventListener('search', () => {
            this.#resultsClose();
        });
    }

    async #epgSearch(searchTerm, e)
    {
        let searchArea = document.getElementById('epg-search');
        let loadingDiv = document.createElement('div');
        let loading = document.createElement('span');

        if (!searchArea.querySelector('.dropdown.search .loading')) {
            if (this.#dropdownElm && this.#dropdownElm.querySelector('p')) {
                this.#dropdownElm.querySelector('p').remove();
            }

            this.#resultsClose();
            loading.classList.add('loading');
            loadingDiv.append(loading)
            this.#resultsOpen(loadingDiv, e);
        }

        let xhr = await this.#app.ajax.post('/api/tv-guide/search', {
            search: searchTerm.trimStart(),
            date: document.querySelector('#epg-search input').dataset.date,
            limit: 6
        });
        let response = JSON.parse(xhr.response);

        if (this.#dropdownElm) {
            this.#resultsClose();
        }

        let resultList = document.createElement('div');

        if ('channels' in response || 'programs' in response) {
            let tabindex = 0;
            if ('channels' in response) {
                resultList.append(this.#addSearchHeading(_('Channels')));
                const nav = document.createElement('nav');
                for (let key in response.channels) {
                    let channel = this.#channelResult(response.channels[key]);
                    channel.tabIndex = tabindex;
                    tabindex++;
                    nav.append(channel);
                }
                resultList.append(nav);
            }

            if ('programs' in response) {
                resultList.append(this.#addSearchHeading(_('Programs')));
                const nav = document.createElement('nav');
                for (let key in response.programs) {
                    let program = this.#programResult(key, response.programs[key]);
                    program.tabIndex = tabindex;
                    tabindex++;
                    nav.append(program);
                }
                resultList.append(nav);
            }
        } else {
            resultList.append(this.#addSearchHeading(_('No results found')));
        }

        this.#resultsOpen(resultList, e);
    }

    #channelResult(channel)
    {
        let channelResult = document.createElement('a');
        let searchBox = document.querySelector('#epg-search input');
        channelResult.id = channel['slug'] + '-result';
        channelResult.classList.add('result', 'channel');
        channelResult.href = searchBox.dataset.baseUrl + channel['url'];
        channelResult.textContent = channel['name'];

        return channelResult;
    }

    #channelLinkFocus(e)
    {
        e.preventDefault();

        if (e.key === 'Enter') {
            e.target.click();
        }
    }

    #programResult(key, title)
    {
        let programResult = document.createElement('a');
        programResult.id = key.toString();
        programResult.classList.add('result', 'program');
        programResult.textContent = title;

        return programResult;
    }

    #bindResultsNavigation()
    {
        let results = this.#dropdownElm.getElementsByClassName('result');
        let focusIndex = 0;

        this.#dropdownElm.addEventListener('keydown', async (e) => {
            e.preventDefault();

            if (e.key === 'Escape') {
                this.#resultsClose();
            }

            if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown' && e.key !== 'Enter') {
                return;
            }

            if (e.key === 'ArrowDown') {
                focusIndex++;
            } else if (e.key === 'ArrowUp') {
                focusIndex--;
            }

            if (focusIndex < 0) {
                results[0].blur();
                focusIndex = 0;
                document.querySelector('#epg-search input').focus();
                return;
            }

            if (focusIndex === results.length) {
                focusIndex = 0;
            }

            results[focusIndex].focus();
        });
    }

    #addSearchHeading(headingText)
    {
        let searchHeading = document.createElement('h4');
        searchHeading.textContent = headingText;
        return searchHeading;
    }

    #resultsOpen(resultList, e)
    {
        let dropdown = new Dropdown(e, e.target, {content: this.#app.loadingElm, className: 'dropdown search'});

        if (!Dropdown.isOpen()) {
            return;
        }

        let template = document.createElement('template');
        template.innerHTML = resultList.innerHTML;

        for (let channel of template.content.querySelectorAll('a.channel')) {
            channel.addEventListener('keydown', this.#channelLinkFocus);
        }

        for (let program of template.content.querySelectorAll('a.program')) {
            program.addEventListener('click', this.#programSearchModalListener.bind(this));
            program.addEventListener('keydown', this.#programSearchModalListener.bind(this));
        }

        dropdown.setContent(template);
        this.#dropdownElm = dropdown.element();

        this.#bindResultsNavigation();
    }

    #resultsClose()
    {
        if (!this.#dropdownElm) {
            return;
        }

        Dropdown.close();
    }
}