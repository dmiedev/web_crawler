import { Controller } from '@hotwired/stimulus';

/** @type {Array<Object>} */
let webPages = null;

/**
 * Node URL to Set of web page IDs
 * @type {Map<String, Set>}
 * */
const nodeWebPageIds = new Map();

/**
 * Node ID to URL
 * @type {Map<number, String>}
 */
const nodeIdToUrl = new Map();

/**
 * URL + URL
 * @type {Set<String>}
 */
const addedEdges = new Set();

/** @type Chart */
let chart = null;

/** @type Set<number> */
const selectedWebPageIds = new Set();

/** @type {EventSource} */
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
    const node = chart.data.datasets[0].data[nodeIndex];

    document.getElementById('nodeDetailLabel').innerText = node.title;
    document.getElementById('nodeDetailUrl').innerText = node.url;
    document.getElementById('nodeDetailCrawlTime').innerText = node.crawlTime?.toLocaleString('en-US') ?? '--';
    document.getElementById('nodeDetail').classList.add('show');

    const list = document.getElementById('nodeDetailWebPagesList');
    list.innerHTML = '';

    if (node.crawlTime === null) {
        list.innerText = '--';
        return;
    }

    const webPageIds = nodeWebPageIds.get(node.url);
    const nodeWebPages = webPages.filter((webPage) => webPageIds.has(webPage._id));
    for (const webPage of nodeWebPages) {
        const listItem = document.createElement('li');
        listItem.classList.add('list-group-item');
        listItem.setAttribute('style', 'justify-content: space-between; display: flex;');
        listItem.innerText = webPage.label;

        const button = document.createElement('button');
        button.setAttribute('type', 'button');
        button.classList.add('btn', 'btn-primary');
        button.innerText = 'Execute';

        listItem.appendChild(button);
        list.appendChild(listItem);
    }
}

function hideNodeDetail() {
    document.getElementById('nodeDetail').classList.remove('show');
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
        const edgeUrls = nodeUrl.concat(linkUrl);
        if (addedEdges.has(edgeUrls)) {
            continue;
        }
        addedEdges.add(edgeUrls);
        chart.data.datasets[0].edges.push({source: nodeUrl, target: linkUrl, webPageId: ownerId});
    }
    const pendingLinks = pendingNodeLinks.get(node.id);
    if (pendingLinks === undefined) {
        return;
    }
    for (const linkUrl of pendingLinks) {
        chart.data.datasets[0].edges.push({source: linkUrl, target: nodeUrl, webPageId: ownerId});
    }
    pendingNodeLinks.delete(node.id);
}


function clearGraph() {
    chart.data.labels = [];
    chart.data.datasets[0].data = [];
    chart.data.datasets[0].edges = [];
    chart.data.datasets[0].pointBackgroundColor = [];
    chart.update();
    nodeWebPageIds.clear();
    nodeIdToUrl.clear();
    addedEdges.clear();
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
            const edgeUrls = nodeUrl.concat(linkUrl);
            if (addedEdges.has(edgeUrls)) {
                continue;
            }
            addedEdges.add(edgeUrls);
            chart.data.datasets[0].edges.push({source: nodeUrl, target: linkUrl, webPageId: ownerId});
        }
    }
    chart.update();
}

function addNode(node) {
    const nodeUrl = convertUrl(node.url);
    const nodeOwnerId = getNodeOwnerId(node);
    nodeIdToUrl.set(node?._id ?? node.id, nodeUrl);
    if (!nodeWebPageIds.has(nodeUrl)) {
        const graphNode = {
            title: getNodeTitle(node),
            url: nodeUrl,
            crawlTime: node.crawlTime != null ? new Date(node.crawlTime) : null,
        };
        const dataset = chart.data.datasets[0];
        dataset.data.push(graphNode);
        dataset.pointBackgroundColor.push(node.crawlTime != null ? 'steelblue' : 'grey');
        chart.data.labels.push(nodeUrl);
        nodeWebPageIds.set(nodeUrl, new Set([nodeOwnerId]));
    } else {
        const webPageIds = nodeWebPageIds.get(nodeUrl);
        webPageIds.add(nodeOwnerId);
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
    const edges = chart.data.datasets[0].edges;

    const removedNodeIndices = [];
    nodes.forEach((node, index) => {
        const webPageIds = nodeWebPageIds.get(node.url);
        webPageIds.delete(webPageId);
        if (webPageIds.size === 0) {
            removedNodeIndices.push(index);
            nodeWebPageIds.delete(node.url);
        }
    });
    removedNodeIndices.toReversed().forEach((index) => {
        chart.data.labels.splice(index, 1);
        chart.data.datasets[0].pointBackgroundColor.splice(index, 1);
        const node = nodes.splice(index, 1)[0];
        delete node.x;
        delete node.y;
        delete node.vx;
        delete node.vy;
    });

    const removedEdgeIndices = [];
    edges.forEach((edge, index) => {
        if (edge.webPageId === webPageId) {
            removedEdgeIndices.push(index);
            addedEdges.delete(edge.source.concat(edge.target));
        }
    });
    removedEdgeIndices.toReversed().forEach((index) => {
        edges.splice(index, 1);
    });

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

}