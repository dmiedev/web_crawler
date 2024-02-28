import {Controller} from '@hotwired/stimulus';
import { Heap } from 'heap-js';

const crawledNodeColor = 'steelblue';
const uncrawledNodeColor = 'grey';

/** @type Array<Object> */
let webPages = null;

/**
 * Node URL to Set of IDs of web pages that have this node
 * @type {Map<String, Set<number>>}
 * */
const nodeWebPageIds = new Map();

/**
 * URL to crawled node objects sorted by crawlTime
 * @type {Map<String, Heap<Object>>}
 */
const urlToNodeHeap = new Map();

/**
 * Node ID to URL
 * @type {Map<number, String>}
 */
const nodeIdToUrl = new Map();

/**
 * Graph edge (source URL + target URL) to
 * Set of IDs of web pages that have this edge
 * @type {Map<String, Set<number>>}
 */
const edgeWebPageIds = new Map();

/** @type Chart */
let chart = null;

/** @type Set<number> */
const selectedWebPageIds = new Set();

/** @type String */
let selectedNodeUrl = null;

/** @type EventSource */
let eventSource = null;

let viewMode = 'web';

export default class extends Controller {
    connect() {
        if (this.element.id === 'graph') {
            this.element.addEventListener('chartjs:pre-connect', this._onPreConnect);
            this.element.addEventListener('chartjs:connect', this._onConnect);
        }
    }

    disconnect() {
        if (this.element.id === 'graph') {
            this.element.removeEventListener('chartjs:pre-connect', this._onPreConnect);
            this.element.removeEventListener('chartjs:connect', this._onConnect);
        }
    }

    async _onPreConnect(event) {
        webPages = await fetchWebPages();
        event.detail.config.options.plugins.tooltip = {
            callbacks: {
                label: function(context) {
                    const title = context.raw.title;
                    const url = context.chart.data.labels[context.dataIndex];
                    if (title != null) {
                        return [title, url];
                    }
                    return [url];
                },
            },
        };
        event.detail.config.options.onClick = function(event, elements) {
            if (elements.length === 1) {
                showNodeDetail(elements[0].index);
            } else {
                hideNodeDetail();
            }
        };
    }

    _onConnect(event) {
        chart = event.detail.chart;
    }

    async updateSelection(event) {
        const isChecked = event.target.checked;
        const webPageId = event.params.webpageid;

        if (isChecked) {
            selectedWebPageIds.add(webPageId);
            const nodes = await fetchWebPageNodes([webPageId]);
            if (nodes != null) {
                addSubgraph(nodes);
            }
        } else {
            selectedWebPageIds.delete(webPageId);
            removeSubgraph(webPageId);
        }

    }

    async switchViewMode(event) {
        const liveModeEnabled = eventSource !== null;
        if (liveModeEnabled) {
            unsubscribeFromMercure();
        }
        viewMode = viewMode === 'domain' ? 'web' : 'domain';
        await reloadGraph();
        if (liveModeEnabled) {
            subscribeToMercure();
        }
    }

    async switchUpdateMode(event) {
        const liveModeEnabled = event.target.checked;
        if (liveModeEnabled) {
            await reloadGraph();
            subscribeToMercure();
        } else {
            unsubscribeFromMercure();
        }
    }
}


function showNodeDetail(nodeIndex) {
    // TODO: onclick -> Stimulus (?)
    const node = chart.data.datasets[0].data[nodeIndex];
    selectedNodeUrl = node.url;
    document.getElementById('node-detail-label').innerText = node.title;
    document.getElementById('node-detail-url').innerText = node.url;
    document.getElementById('node-detail-crawl-time').innerText = node.crawlTime?.toLocaleString('cs-CZ') ?? '--';
    document.getElementById('node-detail').classList.add('show');
    const list = document.getElementById('node-detail-web-pages-list');
    const newButton = document.getElementById('new-web-page-button');
    if (node.crawlTime === null) {
        list.innerText = '--';
        newButton.style.display = 'block';
        newButton.onclick = () => createWebPage(node.url);
        return;
    }
    list.innerHTML = '';
    newButton.style.display = 'none';
    const webPageIds = new Set(urlToNodeHeap.get(node.url).heapArray.map((n) => n.ownerId));
    const nodeWebPages = webPages.filter((webPage) => webPageIds.has(webPage._id));
    for (const webPage of nodeWebPages) {
        const listItem = document.createElement('li');
        listItem.classList.add('list-group-item');
        listItem.innerText = webPage.label;
        const button = document.createElement('button');
        button.setAttribute('type', 'button');
        button.classList.add('btn', 'btn-primary');
        button.innerText = 'Execute';
        button.onclick = () => executeWebPage(webPage._id);
        listItem.appendChild(button);
        list.appendChild(listItem);
    }
}

function hideNodeDetail() {
    selectedNodeUrl = null;
    document.getElementById('node-detail').classList.remove('show');
}

async function createWebPage(url) {
    if (viewMode === 'domain') {
        url = 'https://' + url;
    }
    const webPage = await postWebPage(url);
    if (webPage == null) {
        return;
    }
    hideNodeDetail();
    selectedWebPageIds.add(webPage.id);
    if (eventSource === null) {
        await reloadGraph();
        subscribeToMercure();
        document.getElementById('live-mode').checked = true;
    }
    createWebPageListItem(webPage);
    webPages.push({_id: webPage.id, label: webPage.label});
}

function createWebPageListItem(webPage) {
    const listItem = document.createElement('li');
    const checkbox = document.createElement('input');
    checkbox.setAttribute('type', 'checkbox');
    checkbox.setAttribute('id', `web-page-${webPage.id}`)
    checkbox.setAttribute('data-action', 'change->graph#updateSelection');
    checkbox.setAttribute('data-graph-webpageid-param', webPage.id);
    checkbox.checked = true;
    const label = document.createElement('label');
    label.setAttribute('for', `web-page-${webPage.id}`);
    label.innerText = webPage.label;
    listItem.appendChild(checkbox);
    listItem.appendChild(label);
    document.getElementById('web-page-list').appendChild(listItem);
}


function subscribeToMercure() {
    const url = JSON.parse(document.getElementById("mercure-url").textContent);
    eventSource = new EventSource(url);
    eventSource.onmessage = handleMercureMessage;
}

function unsubscribeFromMercure() {
    eventSource?.close();
    eventSource = null;
}

async function reloadGraph() {
    clearGraph();
    if (selectedWebPageIds.size > 0) {
        const nodes = await fetchWebPageNodes(Array.from(selectedWebPageIds.values()));
        addSubgraph(nodes);
    }
}

function handleMercureMessage(event) {
    const data = JSON.parse(event.data);
    if (data['@type'] === 'Execution') {
        if (data.endTime == null) {
            // New execution has just started
            const webPageId = getIdFromIri(data.webPage);
            if (selectedWebPageIds.has(webPageId)) {
                removeSubgraph(webPageId);
            }
        } else {
            chart.update();
        }
    } else if (data['@type'] === 'WebPageNode') {
        const ownerId = getIdFromIri(data.owner);
        if (!selectedWebPageIds.has(ownerId)) {
            return;
        }
        addNode(data);
        addNodeEdges(data);
        chart.update();
    }
}

/**
 * Node ID to URLs that link to this node
 * @type {Map<number, Array<String>>}
 */
const pendingNodeLinks = new Map();

function addNodeEdges(node) {
    const links = node.links;
    const nodeUrl = convertUrl(node.url);
    const ownerId = getNodeOwnerId(node);
    for (const link of links) {
        const linkId = getIdFromIri(link);
        let linkUrl = nodeIdToUrl.get(linkId);
        if (linkUrl === undefined) {
            if (!pendingNodeLinks.has(linkId)) {
                pendingNodeLinks.set(linkId, []);
            }
            pendingNodeLinks.get(linkId).push(nodeUrl);
            continue;
        }
        addEdge(nodeUrl, linkUrl, ownerId);
    }
    const pendingLinks = pendingNodeLinks.get(node.id);
    if (pendingLinks !== undefined) {
        for (const linkUrl of pendingLinks) {
            addEdge(linkUrl, nodeUrl, ownerId);
        }
        pendingNodeLinks.delete(node.id);
    }
}

function addEdge(sourceUrl, targetUrl, ownerId) {
    const edgeUrls = sourceUrl + targetUrl;
    if (!edgeWebPageIds.has(edgeUrls)) {
        chart.data.datasets[0].edges.push({source: sourceUrl, target: targetUrl});
        edgeWebPageIds.set(edgeUrls, new Set());
    }
    edgeWebPageIds.get(edgeUrls).add(ownerId);
}

function clearGraph() {
    chart.data.labels = [];
    chart.data.datasets[0].data = [];
    chart.data.datasets[0].edges = [];
    chart.data.datasets[0].pointBackgroundColor = [];
    chart.update();
    nodeWebPageIds.clear();
    nodeIdToUrl.clear();
    urlToNodeHeap.clear();
    edgeWebPageIds.clear();
    pendingNodeLinks.clear();
}

function addSubgraph(nodes) {
    for (const node of nodes) {
        addNode(node);
    }
    chart.reset();
    for (const node of nodes) {
        const nodeUrl = convertUrl(node.url);
        const ownerId = getNodeOwnerId(node);
        for (const link of node.links) {
            const linkUrl = convertUrl(link.url);
            addEdge(nodeUrl, linkUrl, ownerId);
        }
    }
    chart.update();
}

const crawlTimeComparator = (a, b) => b.crawlTime.getTime() - a.crawlTime.getTime();

function addNode(node) {
    const url = convertUrl(node.url);
    const crawlTime = node.crawlTime != null ? new Date(node.crawlTime) : null;
    const ownerId = getNodeOwnerId(node);
    const graphNode = {title: getNodeTitle(node), url: url, crawlTime: crawlTime, ownerId: ownerId};
    nodeIdToUrl.set(node?._id ?? node.id, url);
    const dataset = chart.data.datasets[0];
    if (!nodeWebPageIds.has(url)) {
        // Create a new node
        dataset.data.push(graphNode);
        dataset.pointBackgroundColor.push(node.crawlTime != null ? crawledNodeColor : uncrawledNodeColor);
        chart.data.labels.push(url);
        nodeWebPageIds.set(url, new Set([ownerId]));
        const nodeHeap = new Heap(crawlTimeComparator);
        urlToNodeHeap.set(url, nodeHeap);
        if (crawlTime !== null) {
            nodeHeap.add(graphNode);
        }
    } else {
        const webPageIds = nodeWebPageIds.get(url);
        const addedToHeapBefore = webPageIds.has(ownerId);
        webPageIds.add(ownerId);
        if (crawlTime === null || addedToHeapBefore) {
            return;
        }
        const nodeHeap = urlToNodeHeap.get(url);
        nodeHeap.add(graphNode);
        const index = dataset.data.findIndex((n) => n.url === url);
        dataset.data[index] = nodeHeap.peek();
        dataset.pointBackgroundColor[index] = crawledNodeColor;
        if (selectedNodeUrl === url) {
            showNodeDetail(index);
        }
    }
}

const getIdFromIri = (iri) => parseInt(iri.split('/')[3]);

const getNodeOwnerId = (node) => node?.owner?._id ?? getIdFromIri(node.owner);

function getNodeTitle(node) {
    if (viewMode === 'domain') {
        return null;
    }
    return node.crawlTime != null && node.title == null
        ? 'Untitled page'
        : (node.title ?? 'Uncrawled page');
}

const convertUrl = (url) => viewMode === 'web' ? url : (new URL(url)).hostname;

function removeSubgraph(webPageId) {
    const nodes = chart.data.datasets[0].data;
    const colors = chart.data.datasets[0].pointBackgroundColor;

    const removedNodeIndices = [];
    nodes.forEach((node, index) => {
        const webPageIds = nodeWebPageIds.get(node.url);
        const nodeHeap = urlToNodeHeap.get(node.url);
        let removedFromHeap = false;
        if (webPageIds.delete(webPageId) && node.crawlTime !== null) {
            removedFromHeap = nodeHeap.remove({ownerId: webPageId}, (a, b) => a.ownerId === b.ownerId);
        }
        if (webPageIds.size === 0) {
            removedNodeIndices.push(index);
            nodeWebPageIds.delete(node.url);
            urlToNodeHeap.delete(node.url);
            if (selectedNodeUrl === node.url) {
                hideNodeDetail();
            }
        } else {
            if (removedFromHeap) {
                const latestNode = nodeHeap.peek();
                if (latestNode !== undefined) {
                    nodes[index] = latestNode;
                    colors[index] = crawledNodeColor;
                } else {
                    nodes[index] = {title: 'Uncrawled page', url: node.url, crawlTime: null};
                    colors[index] = uncrawledNodeColor;
                }
            }
            if (selectedNodeUrl === node.url) {
                showNodeDetail(index);
            }
        }
    });
    removedNodeIndices.toReversed().forEach((index) => {
        chart.data.labels.splice(index, 1);
        colors.splice(index, 1);
        const node = nodes.splice(index, 1)[0];
        delete node.x;
        delete node.y;
        delete node.vx;
        delete node.vy;
    });

    const edges = chart.data.datasets[0].edges;
    const removedEdgeIndices = [];
    edges.forEach((edge, index) => {
        const edgeUrls = edge.source + edge.target;
        const webPageIds = edgeWebPageIds.get(edgeUrls);
        webPageIds.delete(webPageId);
        if (webPageIds.size === 0) {
            removedEdgeIndices.push(index);
            edgeWebPageIds.delete(edgeUrls);
        }
    });
    removedEdgeIndices.toReversed().forEach((index) => edges.splice(index, 1));

    chart.update();
    chart.reset();
}


async function fetchWebPageNodes(webPageIds) {
    const response = await fetch(window.location.origin + '/api/graphql', {
        method: 'POST',
        headers: {"Content-Type": "application/json", "Accept": "application/json"},
        body: JSON.stringify({
            query: `{ webPageNodes(webPages: [${webPageIds}]) { _id, title, url, crawlTime, links { url }, owner { _id } } }`,
        }),
    });
    if (!response.ok) {
        self.alert('Failed to fetch data!');
        return null;
    }
    const json = await response.json();
    return json.data.webPageNodes;
}

async function fetchWebPages() {
    const response = await fetch(window.location.origin + '/api/graphql', {
        method: 'POST',
        headers: {"Content-Type": "application/json", "Accept": "application/json"},
        body: JSON.stringify({ query: `{ webPages { _id, label } }` }),
    });
    if (!response.ok) {
        self.alert('Failed to fetch data!');
        return null;
    }
    const json = await response.json();
    return json.data.webPages;
}

async function executeWebPage(id) {
    const response = await fetch(window.location.origin + `/api/web_pages/${id}/execute`, {
        method: 'POST',
        headers: {"Content-Type": "application/json", "Accept": "application/json"},
    });
    if (!response.ok) {
        self.alert('Failed to execute web page!');
    } else {
        self.alert('Executed web page!');
    }
}

async function postWebPage(url) {
    const parsedUrl = new URL(url);
    const body = {
        label: parsedUrl.hostname,
        url: url,
        regexp: '/^' + parsedUrl.origin.replaceAll('/', '\\/').replaceAll('.', '\\.') +  '\\/.*/',
        active: true,
        tags: ['graph'],
        periodicity: "2024-12-12T12:00:00",
    };
    const response = await fetch(window.location.origin + '/api/web_pages', {
        method: 'POST',
        headers: {"Content-Type": "application/json", "Accept": "application/json"},
        body: JSON.stringify(body),
    });
    if (!response.ok) {
        self.alert('Failed to submit data!');
        return null;
    }
    return await response.json();
}
