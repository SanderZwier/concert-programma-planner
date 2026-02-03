const { createClient } = require('@libsql/client');

let db = null;

/**
 * Initialize the database connection.
 * Uses Turso (cloud SQLite) if TURSO_DATABASE_URL is set,
 * otherwise falls back to in-memory storage.
 */
async function initDatabase() {
    const url = process.env.TURSO_DATABASE_URL;
    const authToken = process.env.TURSO_AUTH_TOKEN;

    if (!url) {
        console.log('No TURSO_DATABASE_URL set - using in-memory storage (data will not persist across restarts)');
        return false;
    }

    try {
        db = createClient({
            url: url,
            authToken: authToken
        });

        // Create the sessions table if it doesn't exist
        await db.execute(`
            CREATE TABLE IF NOT EXISTS sessions (
                session_id TEXT PRIMARY KEY,
                performers TEXT,
                programme_items TEXT,
                concert_info TEXT,
                updated_at INTEGER
            )
        `);

        console.log('Connected to Turso database - data will persist across restarts');
        return true;
    } catch (error) {
        console.error('Failed to connect to Turso database:', error.message);
        console.log('Falling back to in-memory storage');
        db = null;
        return false;
    }
}

/**
 * Save session state to the database.
 */
async function saveSession(sessionId, performers, programmeItems, concertInfo) {
    if (!db) return false;

    try {
        await db.execute({
            sql: `INSERT OR REPLACE INTO sessions (session_id, performers, programme_items, concert_info, updated_at)
                  VALUES (?, ?, ?, ?, ?)`,
            args: [
                sessionId,
                JSON.stringify(performers || []),
                JSON.stringify(programmeItems || []),
                JSON.stringify(concertInfo || {}),
                Date.now()
            ]
        });
        return true;
    } catch (error) {
        console.error('Failed to save session:', error.message);
        return false;
    }
}

/**
 * Load session state from the database.
 */
async function loadSession(sessionId) {
    if (!db) return null;

    try {
        const result = await db.execute({
            sql: 'SELECT performers, programme_items, concert_info FROM sessions WHERE session_id = ?',
            args: [sessionId]
        });

        if (result.rows.length === 0) {
            return null;
        }

        const row = result.rows[0];
        return {
            performers: JSON.parse(row.performers || '[]'),
            programmeItems: JSON.parse(row.programme_items || '[]'),
            concertInfo: JSON.parse(row.concert_info || '{}')
        };
    } catch (error) {
        console.error('Failed to load session:', error.message);
        return null;
    }
}

/**
 * Delete a session from the database.
 */
async function deleteSession(sessionId) {
    if (!db) return false;

    try {
        await db.execute({
            sql: 'DELETE FROM sessions WHERE session_id = ?',
            args: [sessionId]
        });
        return true;
    } catch (error) {
        console.error('Failed to delete session:', error.message);
        return false;
    }
}

/**
 * Check if database is connected.
 */
function isConnected() {
    return db !== null;
}

module.exports = {
    initDatabase,
    saveSession,
    loadSession,
    deleteSession,
    isConnected
};
