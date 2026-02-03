const mysql = require('mysql2/promise');

let pool = null;

/**
 * Initialize the database connection.
 * Uses MySQL if MYSQL_HOST is set,
 * otherwise falls back to in-memory storage.
 */
async function initDatabase() {
    const host = process.env.MYSQL_HOST;
    const user = process.env.MYSQL_USER;
    const password = process.env.MYSQL_PASSWORD;
    const database = process.env.MYSQL_DATABASE;

    if (!host) {
        console.log('No MYSQL_HOST set - using in-memory storage (data will not persist across restarts)');
        return false;
    }

    try {
        pool = mysql.createPool({
            host: host,
            user: user,
            password: password,
            database: database,
            waitForConnections: true,
            connectionLimit: 5,
            queueLimit: 0
        });

        // Test the connection
        await pool.query('SELECT 1');

        // Create the sessions table if it doesn't exist
        await pool.query(`
            CREATE TABLE IF NOT EXISTS sessions (
                session_id VARCHAR(255) PRIMARY KEY,
                performers TEXT,
                programme_items TEXT,
                concert_info TEXT,
                updated_at BIGINT
            )
        `);

        console.log('Connected to MySQL database - data will persist across restarts');
        return true;
    } catch (error) {
        console.error('Failed to connect to MySQL database:', error.message);
        console.log('Falling back to in-memory storage');
        pool = null;
        return false;
    }
}

/**
 * Save session state to the database.
 */
async function saveSession(sessionId, performers, programmeItems, concertInfo) {
    if (!pool) return false;

    try {
        await pool.query(
            `INSERT INTO sessions (session_id, performers, programme_items, concert_info, updated_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                performers = VALUES(performers),
                programme_items = VALUES(programme_items),
                concert_info = VALUES(concert_info),
                updated_at = VALUES(updated_at)`,
            [
                sessionId,
                JSON.stringify(performers || []),
                JSON.stringify(programmeItems || []),
                JSON.stringify(concertInfo || {}),
                Date.now()
            ]
        );
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
    if (!pool) return null;

    try {
        const [rows] = await pool.query(
            'SELECT performers, programme_items, concert_info FROM sessions WHERE session_id = ?',
            [sessionId]
        );

        if (rows.length === 0) {
            return null;
        }

        const row = rows[0];
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
    if (!pool) return false;

    try {
        await pool.query(
            'DELETE FROM sessions WHERE session_id = ?',
            [sessionId]
        );
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
    return pool !== null;
}

module.exports = {
    initDatabase,
    saveSession,
    loadSession,
    deleteSession,
    isConnected
};
