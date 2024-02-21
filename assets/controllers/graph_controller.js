import { Controller } from '@hotwired/stimulus';

/**
 * Node URL to Set of web page IDs
 * @type {Map<String, Set>}
 * */
const nodeWebPageIds = new Map();

/** @type Chart */
let chart = null;

/** @type Set<integer> */
const selectedWebPageIds = new Set();

let mode = 'web';

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

    _onPreConnect(event) {
        console.log(event.detail.config);

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

    async switchMode(event) {
        mode = mode === 'domain' ? 'web' : 'domain';
        clearGraph();
        if (selectedWebPageIds.size > 0) {
            const nodes = await fetchWebPageNodes(Array.from(selectedWebPageIds.values()));
            addSubgraph(nodes);
        }
    }
}

const url = JSON.parse(document.getElementById("mercure-url").textContent);
const eventSource = new EventSource(url);
eventSource.onmessage = handleMercureMessage;

function handleMercureMessage(messageEvent) {
    const data = JSON.parse(messageEvent.data);
    console.log(data)
}

function clearGraph() {
    chart.data.labels = [];
    chart.data.datasets[0].data = [];
    chart.data.datasets[0].edges = [];
    chart.data.datasets[0].pointBackgroundColor = [];
    chart.update();
    nodeWebPageIds.clear();
}

function addSubgraph(nodes) {
    for (const node of nodes) {
        const nodeUrl = getNodeUrl(node);
        if (!nodeWebPageIds.has(nodeUrl)) {
            const graphNode = {title: getNodeTitle(node), url: nodeUrl};
            const dataset = chart.data.datasets[0];
            dataset.data.push(graphNode);
            dataset.pointBackgroundColor.push(node.crawlTime != null ? 'steelblue' : 'grey');
            chart.data.labels.push(nodeUrl);
            nodeWebPageIds.set(nodeUrl, new Set([node.owner._id]));
        } else {
            const webPageIds = nodeWebPageIds.get(nodeUrl);
            webPageIds.add(node.owner._id);
        }
    }
    chart.reset();
    for (const node of nodes) {
        const nodeUrl = getNodeUrl(node);
        for (const link of node.links) {
            chart.data.datasets[0].edges.push({
                source: nodeUrl,
                target: getNodeUrl(link),
                webPageId: node.owner._id,
            });
        }
    }
    chart.update();
}

function getNodeTitle(node) {
    if (mode === 'domain') {
        return null;
    }
    return node.crawlTime != null && node.title == null
        ? 'Untitled page'
        : (node.title ?? 'Uncrawled page');
}

function getNodeUrl(node) {
    return mode === 'web' ? node.url : (new URL(node.url)).hostname;
}

function removeSubgraph(webPageId) {
    const nodes = chart.data.datasets[0].data;
    const edges = chart.data.datasets[0].edges;

    console.log(chart.data);

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
        }
    });
    removedEdgeIndices.toReversed().forEach((index) => {
        edges.splice(index, 1);
    });

    chart.update();
    chart.reset();
}

// async function fetchWebPageIds() {
//     const response = await fetch(window.location.origin + '/api/graphql', {
//         method: 'POST',
//         headers: {"Content-Type": "application/json", "Accept": "application/json"},
//         body: JSON.stringify({ query: `{ webPages { _id } }` }),
//     });
//     if (!response.ok) {
//         self.alert('Failed to fetch data!');
//         return null;
//     }
//     const json = await response.json();
//     return json.data.webPages;
// }

async function fetchWebPageNodes(webPageIds) {
    const response = await fetch(window.location.origin + '/api/graphql', {
        method: 'POST',
        headers: {"Content-Type": "application/json", "Accept": "application/json"},
        body: JSON.stringify({
            query: `{ webPageNodes(webPages: [${webPageIds}]) { title, url, crawlTime, links { url }, owner { _id } } }`,
        }),
    });
    if (!response.ok) {
        self.alert('Failed to fetch data!');
        return null;
    }
    const json = await response.json();
    return json.data.webPageNodes;
}
