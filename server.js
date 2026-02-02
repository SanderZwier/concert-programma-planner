const http = require('http');
const fs = require('fs');
const path = require('path');
const WebSocket = require('ws');

const PORT = process.env.PORT || 3000;

// HTTP server for serving static files
const server = http.createServer((req, res) => {
    let filePath = req.url === '/' ? '/index.html' : req.url;
    filePath = path.join(__dirname, filePath.split('?')[0]);

    const ext = path.extname(filePath);
    const contentTypes = {
        '.html': 'text/html',
        '.js': 'application/javascript',
        '.css': 'text/css',
        '.json': 'application/json',
        '.png': 'image/png',
        '.jpg': 'image/jpeg',
        '.ico': 'image/x-icon'
    };

    fs.readFile(filePath, (err, content) => {
        if (err) {
            if (err.code === 'ENOENT') {
                // Serve index.html for all routes (SPA support)
                fs.readFile(path.join(__dirname, 'index.html'), (err, content) => {
                    if (err) {
                        res.writeHead(500);
                        res.end('Server Error');
                    } else {
                        res.writeHead(200, { 'Content-Type': 'text/html' });
                        res.end(content, 'utf-8');
                    }
                });
            } else {
                res.writeHead(500);
                res.end('Server Error');
            }
        } else {
            res.writeHead(200, { 'Content-Type': contentTypes[ext] || 'text/plain' });
            res.end(content, 'utf-8');
        }
    });
});

// WebSocket server for real-time collaboration
const wss = new WebSocket.Server({ server });

// Store sessions and their clients
const sessions = new Map();

function generateClientId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
}

wss.on('connection', (ws) => {
    const clientId = generateClientId();
    let currentSessionId = null;

    console.log(`Client ${clientId} connected`);

    // Send welcome message with client ID
    ws.send(JSON.stringify({
        type: 'welcome',
        clientId: clientId
    }));

    ws.on('message', (message) => {
        try {
            const data = JSON.parse(message);

            switch (data.type) {
                case 'join':
                    // Leave current session if any
                    if (currentSessionId && sessions.has(currentSessionId)) {
                        sessions.get(currentSessionId).clients.delete(clientId);
                    }

                    // Join new session
                    currentSessionId = data.sessionId;

                    if (!sessions.has(currentSessionId)) {
                        sessions.set(currentSessionId, {
                            clients: new Map(),
                            state: null
                        });
                    }

                    sessions.get(currentSessionId).clients.set(clientId, ws);

                    console.log(`Client ${clientId} joined session ${currentSessionId}`);

                    // Notify all clients in session about the new client count
                    broadcastToSession(currentSessionId, {
                        type: 'clients',
                        clients: Object.fromEntries(
                            Array.from(sessions.get(currentSessionId).clients.keys())
                                .map(id => [id, { id }])
                        )
                    });

                    // Request state from existing clients if this is a new joiner
                    const session = sessions.get(currentSessionId);
                    if (session.clients.size > 1) {
                        // Ask the first other client to share their state
                        for (const [otherId, otherWs] of session.clients) {
                            if (otherId !== clientId && otherWs.readyState === WebSocket.OPEN) {
                                otherWs.send(JSON.stringify({
                                    type: 'requestState',
                                    requesterId: clientId
                                }));
                                break;
                            }
                        }
                    }
                    break;

                case 'update':
                    // Broadcast update to all other clients in the session
                    if (currentSessionId && sessions.has(currentSessionId)) {
                        broadcastToSession(currentSessionId, {
                            type: 'update',
                            clientId: clientId,
                            performers: data.performers,
                            programmeItems: data.programmeItems
                        }, clientId);
                    }
                    break;

                case 'state':
                    // Forward state to all other clients in the session
                    if (currentSessionId && sessions.has(currentSessionId)) {
                        broadcastToSession(currentSessionId, {
                            type: 'state',
                            performers: data.performers,
                            programmeItems: data.programmeItems
                        }, clientId);
                    }
                    break;
            }
        } catch (error) {
            console.error('Error processing message:', error);
        }
    });

    ws.on('close', () => {
        console.log(`Client ${clientId} disconnected`);

        if (currentSessionId && sessions.has(currentSessionId)) {
            const session = sessions.get(currentSessionId);
            session.clients.delete(clientId);

            // Clean up empty sessions
            if (session.clients.size === 0) {
                sessions.delete(currentSessionId);
                console.log(`Session ${currentSessionId} deleted (empty)`);
            } else {
                // Notify remaining clients
                broadcastToSession(currentSessionId, {
                    type: 'clients',
                    clients: Object.fromEntries(
                        Array.from(session.clients.keys())
                            .map(id => [id, { id }])
                    )
                });
            }
        }
    });

    ws.on('error', (error) => {
        console.error(`WebSocket error for client ${clientId}:`, error);
    });
});

function broadcastToSession(sessionId, message, excludeClientId = null) {
    const session = sessions.get(sessionId);
    if (!session) return;

    const messageStr = JSON.stringify(message);

    for (const [clientId, clientWs] of session.clients) {
        if (clientId !== excludeClientId && clientWs.readyState === WebSocket.OPEN) {
            clientWs.send(messageStr);
        }
    }
}

server.listen(PORT, () => {
    console.log(`Server running on port ${PORT}`);
    console.log(`Open http://localhost:${PORT} in your browser`);
});
