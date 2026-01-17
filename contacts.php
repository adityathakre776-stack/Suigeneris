<?php
/**
 * Emergency Contacts API
 * - GET /contacts.php - Get all contacts
 * - POST /contacts.php - Add contact
 * - PUT /contacts.php?id=X - Update contact
 * - DELETE /contacts.php?id=X - Delete contact
 */

require_once 'config.php';

$user = getAuthUser();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getContacts($user);
        break;
    case 'POST':
        addContact($user);
        break;
    case 'PUT':
        updateContact($user);
        break;
    case 'DELETE':
        deleteContact($user);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getContacts($user) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM emergency_contacts WHERE user_id = ? ORDER BY is_primary DESC, name ASC");
    $stmt->execute([$user['id']]);
    $contacts = $stmt->fetchAll();
    
    sendResponse(['contacts' => $contacts]);
}

function addContact($user) {
    $data = getRequestBody();
    
    if (empty($data['name']) || empty($data['phone'])) {
        sendError('Name and phone are required');
    }
    
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO emergency_contacts (user_id, name, phone, relationship, is_primary) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $user['id'],
        trim($data['name']),
        preg_replace('/[^0-9+]/', '', $data['phone']),
        $data['relationship'] ?? null,
        $data['is_primary'] ?? false
    ]);
    
    sendResponse([
        'message' => 'Contact added',
        'id' => $db->lastInsertId()
    ]);
}

function updateContact($user) {
    $id = $_GET['id'] ?? 0;
    $data = getRequestBody();
    
    if (!$id) {
        sendError('Contact ID required');
    }
    
    $db = getDB();
    
    // Verify ownership
    $stmt = $db->prepare("SELECT id FROM emergency_contacts WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    if (!$stmt->fetch()) {
        sendError('Contact not found', 404);
    }
    
    $updates = [];
    $params = [];
    
    if (isset($data['name'])) {
        $updates[] = "name = ?";
        $params[] = trim($data['name']);
    }
    if (isset($data['phone'])) {
        $updates[] = "phone = ?";
        $params[] = preg_replace('/[^0-9+]/', '', $data['phone']);
    }
    if (isset($data['relationship'])) {
        $updates[] = "relationship = ?";
        $params[] = $data['relationship'];
    }
    if (isset($data['is_primary'])) {
        $updates[] = "is_primary = ?";
        $params[] = (bool)$data['is_primary'];
    }
    
    if (!empty($updates)) {
        $params[] = $id;
        $sql = "UPDATE emergency_contacts SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }
    
    sendResponse(['message' => 'Contact updated']);
}

function deleteContact($user) {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        sendError('Contact ID required');
    }
    
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM emergency_contacts WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    
    if ($stmt->rowCount() === 0) {
        sendError('Contact not found', 404);
    }
    
    sendResponse(['message' => 'Contact deleted']);
}
