-- Migration: WhatsApp Chat System Tables
-- Date: 2025-12-10
-- Description: Creates tables for storing WhatsApp conversations and messages with customer linking

-- WhatsApp Conversations table
CREATE TABLE IF NOT EXISTS whatsapp_conversations (
    id SERIAL PRIMARY KEY,
    chat_id VARCHAR(100) UNIQUE NOT NULL,
    phone_number VARCHAR(20),
    contact_name VARCHAR(255),
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    last_message_preview TEXT,
    last_message_time TIMESTAMP,
    unread_count INTEGER DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- WhatsApp Messages table
CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id SERIAL PRIMARY KEY,
    conversation_id INTEGER REFERENCES whatsapp_conversations(id) ON DELETE CASCADE,
    message_id VARCHAR(100) UNIQUE,
    direction VARCHAR(10) NOT NULL,
    message_type VARCHAR(20) DEFAULT 'text',
    content TEXT,
    media_url TEXT,
    media_mime_type VARCHAR(100),
    sender_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    status VARCHAR(20) DEFAULT 'sent',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_whatsapp_conversations_customer ON whatsapp_conversations(customer_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_conversations_phone ON whatsapp_conversations(phone_number);
CREATE INDEX IF NOT EXISTS idx_whatsapp_messages_conversation ON whatsapp_messages(conversation_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_messages_timestamp ON whatsapp_messages(timestamp);
