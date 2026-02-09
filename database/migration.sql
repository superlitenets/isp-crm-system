-- ============================================================================
-- ISP CRM - Full Database Migration (Idempotent)
-- ============================================================================
-- This is the complete, production-safe database migration for ISP CRM.
-- All statements use idempotent patterns (IF NOT EXISTS, DO blocks with
-- EXCEPTION handlers) so the migration can be safely re-run on existing
-- databases without errors.
--
-- Generated from pg_dump schema and converted to idempotent format.
-- ============================================================================

-- Dumped from database version 16.11 (df20cf9)
-- Dumped by pg_dump version 16.10

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: absent_deduction_rules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.absent_deduction_rules (
    id integer NOT NULL,
    name character varying(100) DEFAULT 'Default Absent Rule'::character varying NOT NULL,
    deduction_type character varying(20) DEFAULT 'daily_rate'::character varying NOT NULL,
    deduction_amount numeric(12,2) DEFAULT 0,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    cutoff_time time without time zone DEFAULT '10:00:00'::time without time zone,
    apply_automatically boolean DEFAULT true
);


--
-- Name: absent_deduction_rules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.absent_deduction_rules_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: absent_deduction_rules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.absent_deduction_rules_id_seq OWNED BY public.absent_deduction_rules.id;


--
-- Name: accounting_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.accounting_settings (
    id integer NOT NULL,
    setting_key character varying(100) NOT NULL,
    setting_value text,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: accounting_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.accounting_settings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: accounting_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.accounting_settings_id_seq OWNED BY public.accounting_settings.id;


--
-- Name: activity_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.activity_logs (
    id integer NOT NULL,
    user_id integer,
    action_type character varying(50) NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id integer,
    entity_reference character varying(100),
    details text,
    ip_address character varying(45),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: activity_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.activity_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: activity_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.activity_logs_id_seq OWNED BY public.activity_logs.id;


--
-- Name: announcement_recipients; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.announcement_recipients (
    id integer NOT NULL,
    announcement_id integer NOT NULL,
    employee_id integer NOT NULL,
    sms_sent boolean DEFAULT false,
    sms_sent_at timestamp without time zone,
    notification_sent boolean DEFAULT false,
    notification_read boolean DEFAULT false,
    notification_read_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: announcement_recipients_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.announcement_recipients_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: announcement_recipients_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.announcement_recipients_id_seq OWNED BY public.announcement_recipients.id;


--
-- Name: announcements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.announcements (
    id integer NOT NULL,
    title character varying(255) NOT NULL,
    message text NOT NULL,
    priority character varying(20) DEFAULT 'normal'::character varying,
    target_audience character varying(50) DEFAULT 'all'::character varying,
    target_branch_id integer,
    target_team_id integer,
    send_sms boolean DEFAULT false,
    send_notification boolean DEFAULT true,
    scheduled_at timestamp without time zone,
    sent_at timestamp without time zone,
    status character varying(20) DEFAULT 'draft'::character varying,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: announcements_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.announcements_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: announcements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.announcements_id_seq OWNED BY public.announcements.id;


--
-- Name: attendance; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.attendance (
    id integer NOT NULL,
    employee_id integer,
    date date NOT NULL,
    clock_in time without time zone,
    clock_out time without time zone,
    status character varying(20) DEFAULT 'present'::character varying,
    hours_worked numeric(5,2),
    overtime_hours numeric(5,2) DEFAULT 0,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    late_minutes integer DEFAULT 0,
    source character varying(50) DEFAULT 'manual'::character varying,
    clock_in_latitude numeric(10,8),
    clock_in_longitude numeric(11,8),
    clock_out_latitude numeric(10,8),
    clock_out_longitude numeric(11,8),
    clock_in_address text,
    clock_out_address text,
    deduction numeric(10,2) DEFAULT 0
);


--
-- Name: attendance_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.attendance_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: attendance_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.attendance_id_seq OWNED BY public.attendance.id;


--
-- Name: attendance_notification_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.attendance_notification_logs (
    id integer NOT NULL,
    employee_id integer,
    notification_template_id integer,
    attendance_date date NOT NULL,
    clock_in_time time without time zone,
    late_minutes integer DEFAULT 0,
    deduction_amount numeric(10,2) DEFAULT 0,
    notification_type character varying(20) DEFAULT 'sms'::character varying,
    phone character varying(50),
    message text,
    status character varying(20) DEFAULT 'pending'::character varying,
    response_data jsonb,
    sent_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: attendance_notification_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.attendance_notification_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: attendance_notification_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.attendance_notification_logs_id_seq OWNED BY public.attendance_notification_logs.id;


--
-- Name: bill_reminders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.bill_reminders (
    id integer NOT NULL,
    bill_id integer NOT NULL,
    reminder_date date NOT NULL,
    sent_at timestamp without time zone,
    sent_to integer,
    notification_type character varying(20) DEFAULT 'both'::character varying,
    is_sent boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: bill_reminders_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.bill_reminders_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: bill_reminders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.bill_reminders_id_seq OWNED BY public.bill_reminders.id;


--
-- Name: biometric_attendance_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.biometric_attendance_logs (
    id integer NOT NULL,
    device_id integer,
    employee_id integer,
    device_user_id character varying(50),
    log_time timestamp without time zone NOT NULL,
    log_type character varying(20) DEFAULT 'check'::character varying,
    verify_mode character varying(20),
    raw_data jsonb,
    processed boolean DEFAULT false,
    attendance_id integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: biometric_attendance_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.biometric_attendance_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: biometric_attendance_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.biometric_attendance_logs_id_seq OWNED BY public.biometric_attendance_logs.id;


--
-- Name: biometric_devices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.biometric_devices (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    device_type character varying(20) NOT NULL,
    ip_address character varying(45) NOT NULL,
    port integer DEFAULT 4370,
    username character varying(100),
    password_encrypted text,
    sync_interval_minutes integer DEFAULT 15,
    is_active boolean DEFAULT true,
    last_sync_at timestamp without time zone,
    last_sync_status character varying(50),
    last_sync_message text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    serial_number character varying(100),
    api_base_url character varying(255),
    last_transaction_id integer,
    company_name character varying(100),
    CONSTRAINT biometric_devices_device_type_check CHECK (((device_type)::text = ANY ((ARRAY['zkteco'::character varying, 'hikvision'::character varying, 'biotime_cloud'::character varying])::text[])))
);


--
-- Name: biometric_devices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.biometric_devices_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: biometric_devices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.biometric_devices_id_seq OWNED BY public.biometric_devices.id;


--
-- Name: branch_employees; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.branch_employees (
    id integer NOT NULL,
    branch_id integer,
    employee_id integer,
    is_primary boolean DEFAULT false,
    assigned_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: branch_employees_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.branch_employees_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: branch_employees_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.branch_employees_id_seq OWNED BY public.branch_employees.id;


--
-- Name: branches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.branches (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(50),
    address text,
    phone character varying(50),
    email character varying(255),
    whatsapp_group character varying(255),
    manager_id integer,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: branches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.branches_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: branches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.branches_id_seq OWNED BY public.branches.id;


--
-- Name: call_center_agent_status; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.call_center_agent_status (
    id integer NOT NULL,
    extension_id integer,
    status character varying(50) DEFAULT 'available'::character varying,
    status_reason character varying(100),
    started_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: call_center_agent_status_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.call_center_agent_status_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: call_center_agent_status_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.call_center_agent_status_id_seq OWNED BY public.call_center_agent_status.id;


--
-- Name: call_center_calls; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.call_center_calls (
    id integer NOT NULL,
    extension character varying(50),
    destination character varying(100),
    direction character varying(20) DEFAULT 'outbound'::character varying,
    status character varying(50) DEFAULT 'pending'::character varying,
    duration integer DEFAULT 0,
    recording_url character varying(500),
    customer_id integer,
    ticket_id integer,
    user_id integer,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    answered_at timestamp without time zone,
    ended_at timestamp without time zone,
    disposition character varying(50) DEFAULT 'NO ANSWER'::character varying,
    call_date timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    uniqueid character varying(100),
    src character varying(50),
    dst character varying(50),
    billsec integer DEFAULT 0,
    started_at timestamp without time zone,
    channel character varying(100),
    context character varying(100),
    dcontext character varying(100),
    extension_id integer,
    queue_id integer
);


--
-- Name: call_center_calls_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.call_center_calls_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: call_center_calls_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.call_center_calls_id_seq OWNED BY public.call_center_calls.id;


--
-- Name: call_center_extensions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.call_center_extensions (
    id integer NOT NULL,
    extension character varying(50) NOT NULL,
    name character varying(100),
    user_id integer,
    secret character varying(100),
    context character varying(50) DEFAULT 'default'::character varying,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: call_center_extensions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.call_center_extensions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: call_center_extensions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.call_center_extensions_id_seq OWNED BY public.call_center_extensions.id;


--
-- Name: call_center_inbound_routes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.call_center_inbound_routes (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    did_pattern character varying(50),
    cid_pattern character varying(50),
    destination_type character varying(50) DEFAULT 'extension'::character varying NOT NULL,
    destination_id character varying(50),
    time_condition_id integer,
    priority integer DEFAULT 0,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    trunk_id integer,
    ringback_tone character varying(100) DEFAULT 'default'::character varying
);


--
-- Name: call_center_inbound_routes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.call_center_inbound_routes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: call_center_inbound_routes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.call_center_inbound_routes_id_seq OWNED BY public.call_center_inbound_routes.id;


--
-- Name: call_center_ivr; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.call_center_ivr (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    announcement text,
    timeout integer DEFAULT 10,
    timeout_destination_type character varying(50) DEFAULT 'hangup'::character varying,
    timeout_destination_id character varying(50),
    invalid_destination_type character varying(50) DEFAULT 'repeat'::character varying,
    invalid_destination_id character varying(50),
    max_loops integer DEFAULT 3,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: call_center_ivr_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.call_center_ivr_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: call_center_ivr_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.call_center_ivr_id_seq OWNED BY public.call_center_ivr.id;


--
-- Name: call_center_ivr_options; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.call_center_ivr_options (
    id integer NOT NULL,
    ivr_id integer,
    digit character varying(2) NOT NULL,
    description text,
    destination_type character varying(50) NOT NULL,
    destination_id character varying(50),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: call_center_ivr_options_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.call_center_ivr_options_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: call_center_ivr_options_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.call_center_ivr_options_id_seq OWNED BY public.call_center_ivr_options.id;


--
-- Name: call_center_outbound_routes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.call_center_outbound_routes (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    dial_pattern character varying(100) NOT NULL,
    prepend character varying(20),
    prefix character varying(20),
    match_cid character varying(50),
    trunk_id integer,
    caller_id character varying(50),
    priority integer DEFAULT 0,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: call_center_outbound_routes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.call_center_outbound_routes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: call_center_outbound_routes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.call_center_outbound_routes_id_seq OWNED BY public.call_center_outbound_routes.id;


--
-- Name: call_center_queue_members; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.call_center_queue_members (
    id integer NOT NULL,
    queue_id integer,
    extension_id integer,
    penalty integer DEFAULT 0,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: call_center_queue_members_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.call_center_queue_members_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: call_center_queue_members_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.call_center_queue_members_id_seq OWNED BY public.call_center_queue_members.id;


--
-- Name: call_center_queues; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.call_center_queues (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    strategy character varying(50) DEFAULT 'ringall'::character varying,
    timeout integer DEFAULT 30,
    wrapuptime integer DEFAULT 5,
    announce_frequency integer DEFAULT 30,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: call_center_queues_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.call_center_queues_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: call_center_queues_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.call_center_queues_id_seq OWNED BY public.call_center_queues.id;


--
-- Name: call_center_ring_group_members; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.call_center_ring_group_members (
    id integer NOT NULL,
    ring_group_id integer,
    extension_id integer,
    priority integer DEFAULT 0,
    created_at timestamp without time zone DEFAULT now()
);


--
-- Name: call_center_ring_group_members_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.call_center_ring_group_members_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: call_center_ring_group_members_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.call_center_ring_group_members_id_seq OWNED BY public.call_center_ring_group_members.id;


--
-- Name: call_center_ring_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.call_center_ring_groups (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    extension character varying(20),
    ring_strategy character varying(50) DEFAULT 'ringall'::character varying,
    ring_time integer DEFAULT 20,
    ringback_tone character varying(100) DEFAULT 'default'::character varying,
    skip_busy boolean DEFAULT false,
    enable_recording boolean DEFAULT false,
    announcement text,
    destination_if_no_answer_type character varying(50) DEFAULT 'hangup'::character varying,
    destination_if_no_answer_id character varying(50),
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


--
-- Name: call_center_ring_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.call_center_ring_groups_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: call_center_ring_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.call_center_ring_groups_id_seq OWNED BY public.call_center_ring_groups.id;


--
-- Name: call_center_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.call_center_settings (
    id integer NOT NULL,
    setting_key character varying(100) NOT NULL,
    setting_value text,
    updated_at timestamp without time zone DEFAULT now()
);


--
-- Name: call_center_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.call_center_settings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: call_center_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.call_center_settings_id_seq OWNED BY public.call_center_settings.id;


--
-- Name: call_center_time_conditions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.call_center_time_conditions (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    time_start time without time zone,
    time_end time without time zone,
    weekdays character varying(20),
    match_destination_type character varying(50),
    match_destination_id character varying(50),
    nomatch_destination_type character varying(50),
    nomatch_destination_id character varying(50),
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: call_center_time_conditions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.call_center_time_conditions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: call_center_time_conditions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.call_center_time_conditions_id_seq OWNED BY public.call_center_time_conditions.id;


--
-- Name: call_center_trunks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.call_center_trunks (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    host character varying(255),
    username character varying(100),
    secret character varying(100),
    context character varying(50) DEFAULT 'from-trunk'::character varying,
    type character varying(50) DEFAULT 'sip'::character varying,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: call_center_trunks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.call_center_trunks_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: call_center_trunks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.call_center_trunks_id_seq OWNED BY public.call_center_trunks.id;


--
-- Name: chart_of_accounts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.chart_of_accounts (
    id integer NOT NULL,
    code character varying(20) NOT NULL,
    name character varying(100) NOT NULL,
    type character varying(50) NOT NULL,
    category character varying(50),
    description text,
    parent_id integer,
    is_system boolean DEFAULT false,
    is_active boolean DEFAULT true,
    balance numeric(15,2) DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: chart_of_accounts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.chart_of_accounts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: chart_of_accounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.chart_of_accounts_id_seq OWNED BY public.chart_of_accounts.id;


--
-- Name: company_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.company_settings (
    id integer NOT NULL,
    setting_key character varying(100) NOT NULL,
    setting_value text,
    setting_type character varying(20) DEFAULT 'text'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: company_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.company_settings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_settings_id_seq OWNED BY public.company_settings.id;


--
-- Name: complaints; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.complaints (
    id integer NOT NULL,
    complaint_number character varying(30) NOT NULL,
    customer_id integer,
    customer_name character varying(100) NOT NULL,
    customer_phone character varying(20) NOT NULL,
    customer_email character varying(100),
    customer_location text,
    category character varying(50) NOT NULL,
    subject character varying(200) NOT NULL,
    description text NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying,
    priority character varying(20) DEFAULT 'medium'::character varying,
    reviewed_by integer,
    reviewed_at timestamp without time zone,
    review_notes text,
    converted_ticket_id integer,
    source character varying(50) DEFAULT 'public'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    created_by integer
);


--
-- Name: complaints_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.complaints_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: complaints_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.complaints_id_seq OWNED BY public.complaints.id;


--
-- Name: customer_payments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.customer_payments (
    id integer NOT NULL,
    payment_number character varying(50) NOT NULL,
    customer_id integer,
    invoice_id integer,
    payment_date date DEFAULT CURRENT_DATE NOT NULL,
    amount numeric(12,2) NOT NULL,
    payment_method character varying(50) NOT NULL,
    mpesa_transaction_id integer,
    mpesa_receipt character varying(50),
    reference character varying(100),
    notes text,
    status character varying(20) DEFAULT 'completed'::character varying,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: customer_payments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.customer_payments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customer_payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customer_payments_id_seq OWNED BY public.customer_payments.id;


--
-- Name: customer_ticket_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.customer_ticket_tokens (
    id integer NOT NULL,
    ticket_id integer NOT NULL,
    customer_id integer,
    token_hash character varying(255) NOT NULL,
    token_lookup character varying(32),
    expires_at timestamp without time zone NOT NULL,
    max_uses integer DEFAULT 50,
    used_count integer DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    last_used_at timestamp without time zone,
    is_active boolean DEFAULT true
);


--
-- Name: customer_ticket_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.customer_ticket_tokens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customer_ticket_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customer_ticket_tokens_id_seq OWNED BY public.customer_ticket_tokens.id;


--
-- Name: customers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.customers (
    id integer NOT NULL,
    account_number character varying(20) NOT NULL,
    name character varying(100) NOT NULL,
    email character varying(100),
    phone character varying(20) NOT NULL,
    address text NOT NULL,
    service_plan character varying(50) NOT NULL,
    connection_status character varying(20) DEFAULT 'active'::character varying,
    installation_date date,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    created_by integer,
    username character varying(100),
    billing_id character varying(100)
);


--
-- Name: customers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.customers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customers_id_seq OWNED BY public.customers.id;


--
-- Name: departments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.departments (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    manager_id integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: departments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.departments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: departments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.departments_id_seq OWNED BY public.departments.id;


--
-- Name: device_interfaces; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.device_interfaces (
    id integer NOT NULL,
    device_id integer,
    if_index integer NOT NULL,
    if_name character varying(100),
    if_descr character varying(255),
    if_type character varying(50),
    if_speed bigint,
    if_status character varying(20),
    in_octets bigint DEFAULT 0,
    out_octets bigint DEFAULT 0,
    in_errors bigint DEFAULT 0,
    out_errors bigint DEFAULT 0,
    last_updated timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: device_interfaces_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.device_interfaces_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: device_interfaces_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.device_interfaces_id_seq OWNED BY public.device_interfaces.id;


--
-- Name: device_monitoring_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.device_monitoring_log (
    id integer NOT NULL,
    device_id integer,
    metric_type character varying(50) NOT NULL,
    metric_name character varying(100),
    metric_value text,
    recorded_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: device_monitoring_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.device_monitoring_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: device_monitoring_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.device_monitoring_log_id_seq OWNED BY public.device_monitoring_log.id;


--
-- Name: device_onus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.device_onus (
    id integer NOT NULL,
    device_id integer,
    onu_id character varying(50) NOT NULL,
    serial_number character varying(50),
    mac_address character varying(17),
    pon_port character varying(20),
    slot integer,
    port integer,
    onu_index integer,
    customer_id integer,
    status character varying(20) DEFAULT 'unknown'::character varying,
    rx_power numeric(10,2),
    tx_power numeric(10,2),
    distance integer,
    description character varying(255),
    profile character varying(100),
    last_online timestamp without time zone,
    last_offline timestamp without time zone,
    last_polled timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: device_onus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.device_onus_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: device_onus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.device_onus_id_seq OWNED BY public.device_onus.id;


--
-- Name: device_user_mapping; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.device_user_mapping (
    id integer NOT NULL,
    device_id integer,
    device_user_id character varying(50) NOT NULL,
    employee_id integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: device_user_mapping_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.device_user_mapping_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: device_user_mapping_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.device_user_mapping_id_seq OWNED BY public.device_user_mapping.id;


--
-- Name: device_vlans; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.device_vlans (
    id integer NOT NULL,
    device_id integer,
    vlan_id integer NOT NULL,
    vlan_name character varying(100),
    vlan_status character varying(20) DEFAULT 'active'::character varying,
    ports text,
    tagged_ports text,
    untagged_ports text,
    in_octets bigint DEFAULT 0,
    out_octets bigint DEFAULT 0,
    in_rate bigint DEFAULT 0,
    out_rate bigint DEFAULT 0,
    last_updated timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: device_vlans_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.device_vlans_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: device_vlans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.device_vlans_id_seq OWNED BY public.device_vlans.id;


--
-- Name: email_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.email_logs (
    id integer NOT NULL,
    recipient character varying(255) NOT NULL,
    subject character varying(500),
    status character varying(20) DEFAULT 'sent'::character varying,
    error_message text,
    sent_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: email_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.email_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: email_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.email_logs_id_seq OWNED BY public.email_logs.id;


--
-- Name: employee_branches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.employee_branches (
    id integer NOT NULL,
    employee_id integer NOT NULL,
    branch_id integer NOT NULL,
    is_primary boolean DEFAULT false,
    assigned_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    assigned_by integer
);


--
-- Name: employee_branches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.employee_branches_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: employee_branches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.employee_branches_id_seq OWNED BY public.employee_branches.id;


--
-- Name: employee_kyc_documents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.employee_kyc_documents (
    id integer NOT NULL,
    employee_id integer NOT NULL,
    document_type character varying(50) NOT NULL,
    document_name character varying(255) NOT NULL,
    file_path character varying(500) NOT NULL,
    uploaded_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    verified_at timestamp without time zone,
    verified_by integer,
    notes text
);


--
-- Name: employee_kyc_documents_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.employee_kyc_documents_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: employee_kyc_documents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.employee_kyc_documents_id_seq OWNED BY public.employee_kyc_documents.id;


--
-- Name: employees; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.employees (
    id integer NOT NULL,
    employee_id character varying(20) NOT NULL,
    user_id integer,
    name character varying(100) NOT NULL,
    email character varying(100),
    phone character varying(20) NOT NULL,
    department_id integer,
    "position" character varying(100) NOT NULL,
    salary numeric(12,2),
    hire_date date,
    employment_status character varying(20) DEFAULT 'active'::character varying,
    emergency_contact character varying(100),
    emergency_phone character varying(20),
    address text,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    office_phone character varying(50),
    passport_photo character varying(255),
    id_number character varying(50),
    passport_number character varying(50),
    next_of_kin_name character varying(255),
    next_of_kin_phone character varying(50),
    next_of_kin_relationship character varying(100),
    date_of_birth date,
    gender character varying(20),
    nationality character varying(100),
    marital_status character varying(50),
    bank_name character varying(100),
    bank_account_no character varying(50),
    payment_method character varying(20) DEFAULT 'mpesa'::character varying
);


--
-- Name: employees_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.employees_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: employees_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.employees_id_seq OWNED BY public.employees.id;


--
-- Name: equipment; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.equipment (
    id integer NOT NULL,
    category_id integer,
    name character varying(200) NOT NULL,
    brand character varying(100),
    model character varying(100),
    serial_number character varying(100),
    mac_address character varying(50),
    purchase_date date,
    purchase_price numeric(10,2),
    warranty_expiry date,
    condition character varying(50) DEFAULT 'new'::character varying,
    status character varying(50) DEFAULT 'available'::character varying,
    location character varying(200),
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    warehouse_id integer,
    location_id integer,
    quantity integer DEFAULT 1,
    sku character varying(50),
    barcode character varying(100),
    lifecycle_status character varying(30) DEFAULT 'in_stock'::character varying,
    last_lifecycle_change timestamp without time zone,
    installed_customer_id integer,
    installed_at timestamp without time zone,
    installed_by integer,
    min_stock_level integer DEFAULT 0,
    max_stock_level integer DEFAULT 0,
    reorder_point integer DEFAULT 0,
    unit_cost numeric(12,2) DEFAULT 0
);


--
-- Name: equipment_assignments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.equipment_assignments (
    id integer NOT NULL,
    equipment_id integer NOT NULL,
    employee_id integer NOT NULL,
    assigned_date date DEFAULT CURRENT_DATE NOT NULL,
    return_date date,
    assigned_by integer,
    notes text,
    status character varying(50) DEFAULT 'assigned'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    customer_id integer
);


--
-- Name: equipment_assignments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.equipment_assignments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipment_assignments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipment_assignments_id_seq OWNED BY public.equipment_assignments.id;


--
-- Name: equipment_categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.equipment_categories (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    parent_id integer,
    item_type character varying(30) DEFAULT 'serialized'::character varying
);


--
-- Name: equipment_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.equipment_categories_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipment_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipment_categories_id_seq OWNED BY public.equipment_categories.id;


--
-- Name: equipment_faults; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.equipment_faults (
    id integer NOT NULL,
    equipment_id integer NOT NULL,
    reported_date date DEFAULT CURRENT_DATE NOT NULL,
    reported_by integer,
    fault_description text NOT NULL,
    severity character varying(50) DEFAULT 'minor'::character varying,
    repair_status character varying(50) DEFAULT 'pending'::character varying,
    repair_date date,
    repair_cost numeric(10,2),
    repair_notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: equipment_faults_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.equipment_faults_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipment_faults_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipment_faults_id_seq OWNED BY public.equipment_faults.id;


--
-- Name: equipment_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.equipment_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipment_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipment_id_seq OWNED BY public.equipment.id;


--
-- Name: equipment_lifecycle_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.equipment_lifecycle_logs (
    id integer NOT NULL,
    equipment_id integer,
    from_status character varying(30),
    to_status character varying(30) NOT NULL,
    changed_by integer,
    reference_type character varying(30),
    reference_id integer,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: equipment_lifecycle_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.equipment_lifecycle_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipment_lifecycle_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipment_lifecycle_logs_id_seq OWNED BY public.equipment_lifecycle_logs.id;


--
-- Name: equipment_loans; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.equipment_loans (
    id integer NOT NULL,
    equipment_id integer NOT NULL,
    customer_id integer NOT NULL,
    loan_date date DEFAULT CURRENT_DATE NOT NULL,
    expected_return_date date,
    actual_return_date date,
    loaned_by integer,
    deposit_amount numeric(10,2) DEFAULT 0,
    deposit_paid boolean DEFAULT false,
    notes text,
    status character varying(50) DEFAULT 'on_loan'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: equipment_loans_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.equipment_loans_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipment_loans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipment_loans_id_seq OWNED BY public.equipment_loans.id;


--
-- Name: expense_categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.expense_categories (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    account_id integer,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: expense_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.expense_categories_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: expense_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.expense_categories_id_seq OWNED BY public.expense_categories.id;


--
-- Name: expenses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.expenses (
    id integer NOT NULL,
    expense_number character varying(50),
    category_id integer,
    vendor_id integer,
    expense_date date DEFAULT CURRENT_DATE NOT NULL,
    amount numeric(12,2) NOT NULL,
    tax_amount numeric(12,2) DEFAULT 0,
    total_amount numeric(12,2) NOT NULL,
    payment_method character varying(50),
    reference character varying(100),
    description text,
    receipt_url text,
    status character varying(20) DEFAULT 'pending'::character varying,
    approved_by integer,
    approved_at timestamp without time zone,
    employee_id integer,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: expenses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.expenses_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: expenses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.expenses_id_seq OWNED BY public.expenses.id;


--
-- Name: genieacs_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.genieacs_config (
    id integer NOT NULL,
    name character varying(255) DEFAULT 'Default'::character varying NOT NULL,
    api_url character varying(500) NOT NULL,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: genieacs_config_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.genieacs_config_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: genieacs_config_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.genieacs_config_id_seq OWNED BY public.genieacs_config.id;


--
-- Name: hr_notification_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.hr_notification_templates (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    category character varying(50) DEFAULT 'attendance'::character varying NOT NULL,
    event_type character varying(50) NOT NULL,
    subject character varying(255),
    sms_template text,
    email_template text,
    is_active boolean DEFAULT true,
    send_sms boolean DEFAULT true,
    send_email boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: hr_notification_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.hr_notification_templates_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: hr_notification_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.hr_notification_templates_id_seq OWNED BY public.hr_notification_templates.id;


--
-- Name: huawei_alerts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_alerts (
    id integer NOT NULL,
    olt_id integer,
    onu_id integer,
    alert_type character varying(50) NOT NULL,
    severity character varying(20) DEFAULT 'info'::character varying,
    title character varying(255) NOT NULL,
    message text,
    is_read boolean DEFAULT false,
    is_resolved boolean DEFAULT false,
    resolved_at timestamp without time zone,
    resolved_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: huawei_alerts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_alerts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_alerts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_alerts_id_seq OWNED BY public.huawei_alerts.id;


--
-- Name: huawei_apartments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_apartments (
    id integer NOT NULL,
    zone_id integer NOT NULL,
    subzone_id integer,
    name character varying(100) NOT NULL,
    address text,
    floors integer,
    units_count integer,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: huawei_apartments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_apartments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_apartments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_apartments_id_seq OWNED BY public.huawei_apartments.id;


--
-- Name: huawei_boards; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_boards (
    id integer NOT NULL,
    olt_id integer NOT NULL,
    slot integer NOT NULL,
    board_name character varying(100),
    board_type character varying(50),
    status character varying(30),
    hardware_version character varying(100),
    software_version character varying(100),
    onu_count integer DEFAULT 0,
    synced_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: huawei_boards_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_boards_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_boards_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_boards_id_seq OWNED BY public.huawei_boards.id;


--
-- Name: huawei_dba_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_dba_profiles (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    profile_id integer,
    bandwidth_type character varying(50) DEFAULT 'type3'::character varying,
    assured_bandwidth integer DEFAULT 10000,
    max_bandwidth integer DEFAULT 100000,
    fixed_bandwidth integer,
    description text,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: huawei_dba_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_dba_profiles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_dba_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_dba_profiles_id_seq OWNED BY public.huawei_dba_profiles.id;


--
-- Name: huawei_odb_units; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_odb_units (
    id integer NOT NULL,
    zone_id integer NOT NULL,
    subzone_id integer,
    apartment_id integer,
    code character varying(50) NOT NULL,
    capacity integer DEFAULT 8,
    ports_used integer DEFAULT 0,
    location_description text,
    latitude numeric(10,8),
    longitude numeric(11,8),
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: huawei_odb_units_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_odb_units_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_odb_units_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_odb_units_id_seq OWNED BY public.huawei_odb_units.id;


--
-- Name: huawei_olt_boards; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_olt_boards (
    id integer NOT NULL,
    olt_id integer NOT NULL,
    slot integer NOT NULL,
    board_name character varying(50),
    status character varying(50),
    subtype character varying(50),
    online_status character varying(20),
    port_count integer DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    hardware_version character varying(50),
    software_version character varying(50),
    serial_number character varying(100),
    board_type character varying(20) DEFAULT 'unknown'::character varying,
    is_enabled boolean DEFAULT true,
    temperature integer
);


--
-- Name: huawei_olt_boards_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_olt_boards_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_olt_boards_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_olt_boards_id_seq OWNED BY public.huawei_olt_boards.id;


--
-- Name: huawei_olt_line_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_olt_line_profiles (
    id integer NOT NULL,
    olt_id integer,
    profile_id integer NOT NULL,
    profile_name character varying(64),
    tcont_count integer DEFAULT 0,
    gem_count integer DEFAULT 0,
    tr069_enabled boolean DEFAULT false,
    raw_config text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: huawei_olt_line_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_olt_line_profiles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_olt_line_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_olt_line_profiles_id_seq OWNED BY public.huawei_olt_line_profiles.id;


--
-- Name: huawei_olt_pon_ports; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_olt_pon_ports (
    id integer NOT NULL,
    olt_id integer NOT NULL,
    port_name character varying(20) NOT NULL,
    port_type character varying(20) DEFAULT 'GPON'::character varying,
    admin_status character varying(20) DEFAULT 'enable'::character varying,
    oper_status character varying(20),
    onu_count integer DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    description character varying(255),
    service_profile_id integer,
    line_profile_id integer,
    native_vlan integer,
    allowed_vlans text,
    max_onus integer DEFAULT 128,
    default_vlan integer
);


--
-- Name: huawei_olt_pon_ports_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_olt_pon_ports_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_olt_pon_ports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_olt_pon_ports_id_seq OWNED BY public.huawei_olt_pon_ports.id;


--
-- Name: huawei_olt_srv_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_olt_srv_profiles (
    id integer NOT NULL,
    olt_id integer,
    profile_id integer NOT NULL,
    profile_name character varying(64),
    eth_ports integer DEFAULT 0,
    pots_ports integer DEFAULT 0,
    wifi_enabled boolean DEFAULT false,
    raw_config text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: huawei_olt_srv_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_olt_srv_profiles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_olt_srv_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_olt_srv_profiles_id_seq OWNED BY public.huawei_olt_srv_profiles.id;


--
-- Name: huawei_olt_uplinks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_olt_uplinks (
    id integer NOT NULL,
    olt_id integer NOT NULL,
    port_name character varying(20) NOT NULL,
    port_type character varying(20),
    admin_status character varying(20) DEFAULT 'enable'::character varying,
    oper_status character varying(20),
    speed character varying(20),
    duplex character varying(20),
    vlan_mode character varying(20),
    pvid integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    description character varying(255),
    allowed_vlans text,
    native_vlan integer,
    is_enabled boolean DEFAULT true,
    mtu integer DEFAULT 1500,
    rx_bytes bigint DEFAULT 0,
    tx_bytes bigint DEFAULT 0,
    rx_errors bigint DEFAULT 0,
    tx_errors bigint DEFAULT 0,
    stats_updated_at timestamp without time zone
);


--
-- Name: huawei_olt_uplinks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_olt_uplinks_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_olt_uplinks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_olt_uplinks_id_seq OWNED BY public.huawei_olt_uplinks.id;


--
-- Name: huawei_olt_vlans; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_olt_vlans (
    id integer NOT NULL,
    olt_id integer NOT NULL,
    vlan_id integer NOT NULL,
    vlan_type character varying(50) DEFAULT 'smart'::character varying,
    description character varying(255),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: huawei_olt_vlans_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_olt_vlans_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_olt_vlans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_olt_vlans_id_seq OWNED BY public.huawei_olt_vlans.id;


--
-- Name: huawei_olts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_olts (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    ip_address character varying(45) NOT NULL,
    port integer DEFAULT 23,
    connection_type character varying(20) DEFAULT 'telnet'::character varying,
    username character varying(100),
    password_encrypted text,
    snmp_community character varying(100) DEFAULT 'public'::character varying,
    snmp_version character varying(10) DEFAULT 'v2c'::character varying,
    snmp_port integer DEFAULT 161,
    vendor character varying(50) DEFAULT 'Huawei'::character varying,
    model character varying(100),
    location character varying(255),
    is_active boolean DEFAULT true,
    last_sync_at timestamp without time zone,
    last_status character varying(20) DEFAULT 'unknown'::character varying,
    uptime character varying(100),
    temperature character varying(50),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    boards_synced_at timestamp without time zone,
    vlans_synced_at timestamp without time zone,
    ports_synced_at timestamp without time zone,
    uplinks_synced_at timestamp without time zone,
    firmware_version character varying(100),
    hardware_model character varying(100),
    software_version character varying(100),
    cpu_usage integer,
    memory_usage integer,
    system_synced_at timestamp without time zone,
    snmp_last_poll timestamp without time zone,
    snmp_sys_name character varying(255),
    snmp_sys_descr text,
    snmp_sys_uptime character varying(100),
    snmp_sys_location character varying(255),
    snmp_status character varying(20) DEFAULT 'unknown'::character varying,
    snmp_read_community character varying(100),
    snmp_write_community character varying(100),
    branch_id integer,
    smartolt_id integer,
    default_line_profile integer,
    cli_protocol character varying(20) DEFAULT 'telnet'::character varying,
    ssh_port integer DEFAULT 22
);


--
-- Name: huawei_olts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_olts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_olts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_olts_id_seq OWNED BY public.huawei_olts.id;


--
-- Name: huawei_onu_mgmt_ips; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_onu_mgmt_ips (
    id integer NOT NULL,
    olt_id integer NOT NULL,
    onu_id integer,
    ip_address character varying(45) NOT NULL,
    subnet_mask character varying(45),
    gateway character varying(45),
    vlan_id integer,
    ip_type character varying(20) DEFAULT 'static'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: huawei_onu_mgmt_ips_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_onu_mgmt_ips_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_onu_mgmt_ips_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_onu_mgmt_ips_id_seq OWNED BY public.huawei_onu_mgmt_ips.id;


--
-- Name: huawei_onu_service_vlans; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_onu_service_vlans (
    id integer NOT NULL,
    onu_id integer NOT NULL,
    vlan_id integer NOT NULL,
    vlan_name character varying(100),
    interface_type character varying(20) DEFAULT 'wifi'::character varying,
    port_mode character varying(20) DEFAULT 'access'::character varying,
    is_native boolean DEFAULT false,
    priority integer DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT huawei_onu_service_vlans_interface_type_check CHECK (((interface_type)::text = ANY ((ARRAY['wifi'::character varying, 'eth'::character varying, 'all'::character varying])::text[]))),
    CONSTRAINT huawei_onu_service_vlans_port_mode_check CHECK (((port_mode)::text = ANY ((ARRAY['access'::character varying, 'trunk'::character varying])::text[])))
);


--
-- Name: huawei_onu_service_vlans_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_onu_service_vlans_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_onu_service_vlans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_onu_service_vlans_id_seq OWNED BY public.huawei_onu_service_vlans.id;


--
-- Name: huawei_onu_tr069_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_onu_tr069_config (
    onu_id integer NOT NULL,
    config_data text,
    status character varying(20) DEFAULT 'pending'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone,
    applied_at timestamp without time zone
);


--
-- Name: huawei_onu_tr069_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_onu_tr069_logs (
    id integer NOT NULL,
    onu_id integer,
    olt_id integer,
    genieacs_id character varying(255),
    task_type character varying(50) NOT NULL,
    task_name character varying(500),
    terminal character varying(50) DEFAULT 'ACS'::character varying,
    result character varying(20) DEFAULT 'Pending'::character varying,
    request_data text,
    response_data text,
    error_message text,
    http_code integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: huawei_onu_tr069_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_onu_tr069_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_onu_tr069_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_onu_tr069_logs_id_seq OWNED BY public.huawei_onu_tr069_logs.id;


--
-- Name: huawei_onu_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_onu_types (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    model character varying(100),
    model_aliases text,
    vendor character varying(100) DEFAULT 'Huawei'::character varying,
    eth_ports integer DEFAULT 1,
    pots_ports integer DEFAULT 0,
    wifi_capable boolean DEFAULT false,
    wifi_dual_band boolean DEFAULT false,
    catv_port boolean DEFAULT false,
    usb_port boolean DEFAULT false,
    pon_type character varying(20) DEFAULT 'GPON'::character varying,
    default_mode character varying(20) DEFAULT 'bridge'::character varying,
    tcont_count integer DEFAULT 1,
    gemport_count integer DEFAULT 1,
    recommended_line_profile character varying(100),
    recommended_srv_profile character varying(100),
    omci_capable boolean DEFAULT true,
    tr069_capable boolean DEFAULT true,
    description text,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    equipment_id character varying(64)
);


--
-- Name: huawei_onu_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_onu_types_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_onu_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_onu_types_id_seq OWNED BY public.huawei_onu_types.id;


--
-- Name: huawei_onus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_onus (
    id integer NOT NULL,
    olt_id integer,
    customer_id integer,
    sn character varying(100) NOT NULL,
    name character varying(100),
    description text,
    frame bigint DEFAULT 0,
    slot bigint,
    port bigint,
    onu_id bigint,
    onu_type character varying(100),
    mac_address character varying(17),
    status character varying(30) DEFAULT 'offline'::character varying,
    rx_power numeric(10,2),
    tx_power numeric(10,2),
    distance integer,
    last_down_cause character varying(100),
    last_down_time timestamp without time zone,
    last_up_time timestamp without time zone,
    service_profile_id integer,
    line_profile character varying(100),
    srv_profile character varying(100),
    is_authorized boolean DEFAULT false,
    firmware_version character varying(100),
    hardware_version character varying(100),
    software_version character varying(100),
    ip_address character varying(45),
    config_state character varying(50),
    run_state character varying(50),
    auth_type character varying(20) DEFAULT 'sn'::character varying,
    password character varying(100),
    additional_info text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    vlan_id integer,
    vlan_priority integer DEFAULT 0,
    ip_mode character varying(20) DEFAULT 'dhcp'::character varying,
    line_profile_id integer,
    srv_profile_id integer,
    tr069_profile_id integer,
    zone character varying(100),
    area character varying(100),
    customer_name character varying(255),
    auth_date date,
    apartment character varying(100),
    odb character varying(50),
    zone_id integer,
    subzone_id integer,
    apartment_id integer,
    odb_id integer,
    olt_sync_pending boolean DEFAULT false,
    optical_updated_at timestamp without time zone,
    onu_type_id integer,
    tr069_device_id character varying(255),
    tr069_serial character varying(100),
    tr069_ip character varying(45),
    tr069_status character varying(50) DEFAULT 'pending'::character varying,
    tr069_last_inform timestamp without time zone,
    discovered_eqid character varying(100),
    port_config jsonb,
    smartolt_external_id character varying(100),
    provisioning_stage integer DEFAULT 0,
    wan_mode character varying(20),
    attached_vlans text,
    phone character varying(50),
    latitude numeric(10,8),
    longitude numeric(11,8),
    installation_date date,
    pppoe_username character varying(100),
    pppoe_password character varying(100),
    address text,
    uptime character varying(100),
    online_since timestamp without time zone,
    ont_ip character varying(45),
    genieacs_id character varying(255) DEFAULT NULL::character varying,
    last_provision_at timestamp without time zone,
    provision_state character varying(50) DEFAULT 'DISCOVERED'::character varying,
    wan_device_path character varying(255),
    wan_conn_path character varying(255),
    ppp_path character varying(255),
    wan_profile_id integer DEFAULT 1,
    onu_mode character varying(20) DEFAULT 'router'::character varying,
    snmp_status character varying(50) DEFAULT NULL::character varying
);


--
-- Name: huawei_onus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_onus_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_onus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_onus_id_seq OWNED BY public.huawei_onus.id;


--
-- Name: huawei_pon_ports; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_pon_ports (
    id integer NOT NULL,
    olt_id integer NOT NULL,
    frame integer DEFAULT 0,
    slot integer NOT NULL,
    port integer NOT NULL,
    admin_status character varying(30),
    oper_status character varying(30),
    onu_count integer DEFAULT 0,
    max_onus integer DEFAULT 128,
    description text,
    synced_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    default_vlan integer
);


--
-- Name: huawei_pon_ports_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_pon_ports_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_pon_ports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_pon_ports_id_seq OWNED BY public.huawei_pon_ports.id;


--
-- Name: huawei_port_vlans; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_port_vlans (
    id integer NOT NULL,
    olt_id integer NOT NULL,
    port_name character varying(20) NOT NULL,
    port_type character varying(10) DEFAULT 'pon'::character varying NOT NULL,
    vlan_id integer NOT NULL,
    vlan_mode character varying(20) DEFAULT 'tag'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: huawei_port_vlans_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_port_vlans_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_port_vlans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_port_vlans_id_seq OWNED BY public.huawei_port_vlans.id;


--
-- Name: huawei_provisioning_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_provisioning_logs (
    id integer NOT NULL,
    olt_id integer,
    onu_id integer,
    action character varying(50) NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying,
    message text,
    details text,
    command_sent text,
    command_response text,
    user_id integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: huawei_provisioning_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_provisioning_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_provisioning_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_provisioning_logs_id_seq OWNED BY public.huawei_provisioning_logs.id;


--
-- Name: huawei_service_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_service_profiles (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    profile_type character varying(50) DEFAULT 'internet'::character varying,
    vlan_id integer,
    vlan_mode character varying(20) DEFAULT 'tag'::character varying,
    speed_profile_up character varying(50),
    speed_profile_down character varying(50),
    qos_profile character varying(100),
    gem_port integer,
    tcont_profile character varying(100),
    line_profile character varying(100),
    srv_profile character varying(100),
    native_vlan integer,
    additional_config text,
    is_default boolean DEFAULT false,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    tr069_vlan integer,
    tr069_profile_id integer,
    tr069_gem_port integer DEFAULT 2
);


--
-- Name: huawei_service_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_service_profiles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_service_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_service_profiles_id_seq OWNED BY public.huawei_service_profiles.id;


--
-- Name: huawei_service_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_service_templates (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    downstream_bandwidth integer DEFAULT 100,
    upstream_bandwidth integer DEFAULT 50,
    bandwidth_unit character varying(10) DEFAULT 'mbps'::character varying,
    vlan_id integer,
    vlan_mode character varying(20) DEFAULT 'tag'::character varying,
    qos_profile character varying(100),
    line_profile_id integer,
    service_profile_id integer,
    iptv_enabled boolean DEFAULT false,
    voip_enabled boolean DEFAULT false,
    tr069_enabled boolean DEFAULT false,
    is_default boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: huawei_service_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_service_templates_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_service_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_service_templates_id_seq OWNED BY public.huawei_service_templates.id;


--
-- Name: huawei_subzones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_subzones (
    id integer NOT NULL,
    zone_id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: huawei_subzones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_subzones_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_subzones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_subzones_id_seq OWNED BY public.huawei_subzones.id;


--
-- Name: huawei_uplinks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_uplinks (
    id integer NOT NULL,
    olt_id integer NOT NULL,
    frame integer DEFAULT 0,
    slot integer NOT NULL,
    port integer NOT NULL,
    port_type character varying(50),
    admin_status character varying(30),
    oper_status character varying(30),
    speed character varying(30),
    duplex character varying(30),
    vlan_mode character varying(30),
    allowed_vlans text,
    pvid integer,
    description text,
    synced_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: huawei_uplinks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_uplinks_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_uplinks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_uplinks_id_seq OWNED BY public.huawei_uplinks.id;


--
-- Name: huawei_vlans; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_vlans (
    id integer NOT NULL,
    olt_id integer,
    vlan_id integer NOT NULL,
    vlan_type character varying(20) DEFAULT 'smart'::character varying,
    attribute character varying(50) DEFAULT 'common'::character varying,
    standard_port_count integer DEFAULT 0,
    service_port_count integer DEFAULT 0,
    vlan_connect_count integer DEFAULT 0,
    description character varying(255),
    is_management boolean DEFAULT false,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    is_multicast boolean DEFAULT false,
    is_voip boolean DEFAULT false,
    dhcp_snooping boolean DEFAULT false,
    lan_to_lan boolean DEFAULT false,
    is_tr069 boolean DEFAULT false
);


--
-- Name: huawei_vlans_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_vlans_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_vlans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_vlans_id_seq OWNED BY public.huawei_vlans.id;


--
-- Name: huawei_zones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.huawei_zones (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: huawei_zones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.huawei_zones_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: huawei_zones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.huawei_zones_id_seq OWNED BY public.huawei_zones.id;


--
-- Name: interface_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.interface_history (
    id integer NOT NULL,
    interface_id integer,
    in_octets bigint DEFAULT 0,
    out_octets bigint DEFAULT 0,
    in_rate bigint DEFAULT 0,
    out_rate bigint DEFAULT 0,
    in_errors bigint DEFAULT 0,
    out_errors bigint DEFAULT 0,
    recorded_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: interface_history_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.interface_history_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: interface_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.interface_history_id_seq OWNED BY public.interface_history.id;


--
-- Name: inventory_audit_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_audit_items (
    id integer NOT NULL,
    audit_id integer,
    equipment_id integer,
    category_id integer,
    expected_qty integer DEFAULT 0,
    actual_qty integer DEFAULT 0,
    variance integer DEFAULT 0,
    notes text,
    verified_by integer,
    verified_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_audit_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_audit_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_audit_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_audit_items_id_seq OWNED BY public.inventory_audit_items.id;


--
-- Name: inventory_audits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_audits (
    id integer NOT NULL,
    audit_number character varying(30) NOT NULL,
    warehouse_id integer,
    audit_type character varying(30) DEFAULT 'full'::character varying,
    scheduled_date date,
    completed_date date,
    status character varying(20) DEFAULT 'pending'::character varying,
    notes text,
    created_by integer,
    completed_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_audits_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_audits_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_audits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_audits_id_seq OWNED BY public.inventory_audits.id;


--
-- Name: inventory_locations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_locations (
    id integer NOT NULL,
    warehouse_id integer,
    name character varying(100) NOT NULL,
    code character varying(50),
    type character varying(30) DEFAULT 'shelf'::character varying,
    capacity integer,
    notes text,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_locations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_locations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_locations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_locations_id_seq OWNED BY public.inventory_locations.id;


--
-- Name: inventory_loss_reports; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_loss_reports (
    id integer NOT NULL,
    report_number character varying(30) NOT NULL,
    equipment_id integer,
    reported_by integer,
    employee_id integer,
    loss_type character varying(30) DEFAULT 'lost'::character varying NOT NULL,
    loss_date date NOT NULL,
    description text NOT NULL,
    estimated_value numeric(12,2),
    investigation_status character varying(20) DEFAULT 'pending'::character varying,
    investigation_notes text,
    resolved_by integer,
    resolved_at timestamp without time zone,
    resolution character varying(50),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_loss_reports_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_loss_reports_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_loss_reports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_loss_reports_id_seq OWNED BY public.inventory_loss_reports.id;


--
-- Name: inventory_po_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_po_items (
    id integer NOT NULL,
    po_id integer,
    category_id integer,
    item_name character varying(200) NOT NULL,
    quantity integer NOT NULL,
    unit_price numeric(12,2) DEFAULT 0,
    received_qty integer DEFAULT 0,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_po_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_po_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_po_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_po_items_id_seq OWNED BY public.inventory_po_items.id;


--
-- Name: inventory_purchase_orders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_purchase_orders (
    id integer NOT NULL,
    po_number character varying(30) NOT NULL,
    supplier_name character varying(200),
    supplier_contact character varying(100),
    order_date date NOT NULL,
    expected_date date,
    status character varying(20) DEFAULT 'pending'::character varying,
    total_amount numeric(12,2) DEFAULT 0,
    notes text,
    created_by integer,
    approved_by integer,
    approved_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_purchase_orders_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_purchase_orders_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_purchase_orders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_purchase_orders_id_seq OWNED BY public.inventory_purchase_orders.id;


--
-- Name: inventory_receipt_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_receipt_items (
    id integer NOT NULL,
    receipt_id integer,
    po_item_id integer,
    equipment_id integer,
    category_id integer,
    item_name character varying(200) NOT NULL,
    quantity integer DEFAULT 1 NOT NULL,
    serial_number character varying(100),
    mac_address character varying(50),
    condition character varying(20) DEFAULT 'new'::character varying,
    location_id integer,
    unit_cost numeric(12,2) DEFAULT 0,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_receipt_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_receipt_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_receipt_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_receipt_items_id_seq OWNED BY public.inventory_receipt_items.id;


--
-- Name: inventory_receipts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_receipts (
    id integer NOT NULL,
    receipt_number character varying(30) NOT NULL,
    po_id integer,
    warehouse_id integer,
    receipt_date date NOT NULL,
    supplier_name character varying(200),
    delivery_note character varying(100),
    status character varying(20) DEFAULT 'pending'::character varying,
    notes text,
    received_by integer,
    verified_by integer,
    verified_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_receipts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_receipts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_receipts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_receipts_id_seq OWNED BY public.inventory_receipts.id;


--
-- Name: inventory_return_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_return_items (
    id integer NOT NULL,
    return_id integer,
    equipment_id integer,
    request_item_id integer,
    quantity integer DEFAULT 1,
    condition character varying(20) DEFAULT 'good'::character varying,
    location_id integer,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_return_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_return_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_return_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_return_items_id_seq OWNED BY public.inventory_return_items.id;


--
-- Name: inventory_returns; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_returns (
    id integer NOT NULL,
    return_number character varying(30) NOT NULL,
    request_id integer,
    returned_by integer,
    warehouse_id integer,
    return_date date NOT NULL,
    return_type character varying(30) DEFAULT 'unused'::character varying,
    status character varying(20) DEFAULT 'pending'::character varying,
    notes text,
    received_by integer,
    received_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_returns_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_returns_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_returns_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_returns_id_seq OWNED BY public.inventory_returns.id;


--
-- Name: inventory_rma; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_rma (
    id integer NOT NULL,
    rma_number character varying(30) NOT NULL,
    equipment_id integer,
    fault_id integer,
    vendor_name character varying(200),
    vendor_contact character varying(100),
    status character varying(20) DEFAULT 'pending'::character varying,
    shipped_date date,
    received_date date,
    resolution character varying(50),
    resolution_notes text,
    replacement_equipment_id integer,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_rma_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_rma_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_rma_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_rma_id_seq OWNED BY public.inventory_rma.id;


--
-- Name: inventory_stock_levels; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_stock_levels (
    id integer NOT NULL,
    category_id integer,
    warehouse_id integer,
    min_quantity integer DEFAULT 0,
    max_quantity integer DEFAULT 100,
    reorder_point integer DEFAULT 10,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_stock_levels_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_stock_levels_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_stock_levels_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_stock_levels_id_seq OWNED BY public.inventory_stock_levels.id;


--
-- Name: inventory_stock_movements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_stock_movements (
    id integer NOT NULL,
    equipment_id integer,
    movement_type character varying(30) NOT NULL,
    from_location_id integer,
    to_location_id integer,
    from_warehouse_id integer,
    to_warehouse_id integer,
    quantity integer DEFAULT 1,
    reference_type character varying(30),
    reference_id integer,
    notes text,
    performed_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_stock_movements_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_stock_movements_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_stock_movements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_stock_movements_id_seq OWNED BY public.inventory_stock_movements.id;


--
-- Name: inventory_stock_request_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_stock_request_items (
    id integer NOT NULL,
    request_id integer,
    equipment_id integer,
    category_id integer,
    item_name character varying(200),
    quantity_requested integer DEFAULT 1 NOT NULL,
    quantity_approved integer DEFAULT 0,
    quantity_picked integer DEFAULT 0,
    quantity_used integer DEFAULT 0,
    quantity_returned integer DEFAULT 0,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_stock_request_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_stock_request_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_stock_request_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_stock_request_items_id_seq OWNED BY public.inventory_stock_request_items.id;


--
-- Name: inventory_stock_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_stock_requests (
    id integer NOT NULL,
    request_number character varying(30) NOT NULL,
    requested_by integer,
    warehouse_id integer,
    request_type character varying(30) DEFAULT 'technician'::character varying NOT NULL,
    ticket_id integer,
    customer_id integer,
    priority character varying(20) DEFAULT 'normal'::character varying,
    status character varying(20) DEFAULT 'pending'::character varying,
    required_date date,
    notes text,
    approved_by integer,
    approved_at timestamp without time zone,
    picked_by integer,
    picked_at timestamp without time zone,
    handed_to integer,
    handover_at timestamp without time zone,
    handover_signature text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_stock_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_stock_requests_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_stock_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_stock_requests_id_seq OWNED BY public.inventory_stock_requests.id;


--
-- Name: inventory_thresholds; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_thresholds (
    id integer NOT NULL,
    category_id integer,
    warehouse_id integer,
    min_quantity integer DEFAULT 5 NOT NULL,
    max_quantity integer DEFAULT 100,
    reorder_point integer DEFAULT 10 NOT NULL,
    reorder_quantity integer DEFAULT 20,
    notify_on_low boolean DEFAULT true,
    notify_on_excess boolean DEFAULT false,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_thresholds_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_thresholds_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_thresholds_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_thresholds_id_seq OWNED BY public.inventory_thresholds.id;


--
-- Name: inventory_usage; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_usage (
    id integer NOT NULL,
    equipment_id integer,
    request_item_id integer,
    ticket_id integer,
    customer_id integer,
    employee_id integer,
    job_type character varying(50) DEFAULT 'installation'::character varying NOT NULL,
    quantity integer DEFAULT 1,
    usage_date date NOT NULL,
    notes text,
    recorded_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_usage_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_usage_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_usage_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_usage_id_seq OWNED BY public.inventory_usage.id;


--
-- Name: inventory_warehouses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.inventory_warehouses (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    code character varying(20) NOT NULL,
    type character varying(30) DEFAULT 'depot'::character varying NOT NULL,
    address text,
    phone character varying(20),
    manager_id integer,
    is_active boolean DEFAULT true,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: inventory_warehouses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.inventory_warehouses_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventory_warehouses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventory_warehouses_id_seq OWNED BY public.inventory_warehouses.id;


--
-- Name: invoice_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.invoice_items (
    id integer NOT NULL,
    invoice_id integer,
    product_id integer,
    description text NOT NULL,
    quantity numeric(10,2) DEFAULT 1,
    unit_price numeric(12,2) NOT NULL,
    tax_rate_id integer,
    tax_amount numeric(12,2) DEFAULT 0,
    discount_percent numeric(5,2) DEFAULT 0,
    line_total numeric(12,2) NOT NULL,
    sort_order integer DEFAULT 0
);


--
-- Name: invoice_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.invoice_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: invoice_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.invoice_items_id_seq OWNED BY public.invoice_items.id;


--
-- Name: invoices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.invoices (
    id integer NOT NULL,
    invoice_number character varying(50) NOT NULL,
    customer_id integer,
    order_id integer,
    ticket_id integer,
    issue_date date DEFAULT CURRENT_DATE NOT NULL,
    due_date date NOT NULL,
    status character varying(20) DEFAULT 'draft'::character varying,
    subtotal numeric(12,2) DEFAULT 0,
    tax_amount numeric(12,2) DEFAULT 0,
    discount_amount numeric(12,2) DEFAULT 0,
    total_amount numeric(12,2) DEFAULT 0,
    amount_paid numeric(12,2) DEFAULT 0,
    balance_due numeric(12,2) DEFAULT 0,
    currency character varying(10) DEFAULT 'KES'::character varying,
    notes text,
    terms text,
    is_recurring boolean DEFAULT false,
    recurring_interval character varying(20),
    next_recurring_date date,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: invoices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.invoices_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: invoices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.invoices_id_seq OWNED BY public.invoices.id;


--
-- Name: isp_locations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.isp_locations (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    code character varying(20),
    description text,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: isp_locations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.isp_locations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: isp_locations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.isp_locations_id_seq OWNED BY public.isp_locations.id;


--
-- Name: isp_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.isp_settings (
    id integer NOT NULL,
    setting_key character varying(100) NOT NULL,
    setting_value text,
    setting_type character varying(50) DEFAULT 'text'::character varying,
    category character varying(50) DEFAULT 'general'::character varying,
    description text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: isp_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.isp_settings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: isp_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.isp_settings_id_seq OWNED BY public.isp_settings.id;


--
-- Name: isp_sub_locations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.isp_sub_locations (
    id integer NOT NULL,
    location_id integer NOT NULL,
    name character varying(100) NOT NULL,
    code character varying(20),
    description text,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: isp_sub_locations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.isp_sub_locations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: isp_sub_locations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.isp_sub_locations_id_seq OWNED BY public.isp_sub_locations.id;


--
-- Name: late_rules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.late_rules (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    work_start_time time without time zone DEFAULT '09:00:00'::time without time zone NOT NULL,
    grace_minutes integer DEFAULT 15,
    deduction_tiers jsonb DEFAULT '[]'::jsonb NOT NULL,
    currency character varying(10) DEFAULT 'KES'::character varying,
    apply_to_department_id integer,
    is_default boolean DEFAULT false,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: late_rules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.late_rules_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: late_rules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.late_rules_id_seq OWNED BY public.late_rules.id;


--
-- Name: leave_balances; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.leave_balances (
    id integer NOT NULL,
    employee_id integer,
    leave_type_id integer,
    year integer NOT NULL,
    entitled_days numeric(5,2) DEFAULT 0,
    used_days numeric(5,2) DEFAULT 0,
    carried_over numeric(5,2) DEFAULT 0,
    accrued_days numeric(5,2) DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    pending_days numeric(5,2) DEFAULT 0,
    carried_over_days numeric(5,2) DEFAULT 0,
    adjusted_days numeric(5,2) DEFAULT 0
);


--
-- Name: leave_balances_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.leave_balances_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: leave_balances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.leave_balances_id_seq OWNED BY public.leave_balances.id;


--
-- Name: leave_calendar; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.leave_calendar (
    id integer NOT NULL,
    date date NOT NULL,
    name character varying(255) NOT NULL,
    is_public_holiday boolean DEFAULT false,
    branch_id integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: leave_calendar_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.leave_calendar_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: leave_calendar_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.leave_calendar_id_seq OWNED BY public.leave_calendar.id;


--
-- Name: leave_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.leave_requests (
    id integer NOT NULL,
    employee_id integer,
    leave_type_id integer,
    start_date date NOT NULL,
    end_date date NOT NULL,
    days_requested numeric(5,2) NOT NULL,
    reason text,
    status character varying(20) DEFAULT 'pending'::character varying,
    approved_by integer,
    approved_at timestamp without time zone,
    rejection_reason text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: leave_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.leave_requests_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: leave_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.leave_requests_id_seq OWNED BY public.leave_requests.id;


--
-- Name: leave_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.leave_types (
    id integer NOT NULL,
    name character varying(50) NOT NULL,
    code character varying(20) NOT NULL,
    days_per_year integer DEFAULT 0,
    is_paid boolean DEFAULT true,
    requires_approval boolean DEFAULT true,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: leave_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.leave_types_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: leave_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.leave_types_id_seq OWNED BY public.leave_types.id;


--
-- Name: license_activations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.license_activations (
    id integer NOT NULL,
    license_id integer,
    activation_token character varying(64) NOT NULL,
    domain character varying(255),
    server_ip character varying(45),
    server_hostname character varying(255),
    hardware_id character varying(255),
    php_version character varying(20),
    os_info character varying(255),
    first_activated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    last_seen_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    last_validated_at timestamp without time zone,
    is_active boolean DEFAULT true,
    deactivated_at timestamp without time zone,
    deactivation_reason text,
    metadata jsonb DEFAULT '{}'::jsonb
);


--
-- Name: license_activations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.license_activations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: license_activations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.license_activations_id_seq OWNED BY public.license_activations.id;


--
-- Name: license_customers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.license_customers (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    company character varying(255),
    phone character varying(50),
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: license_customers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.license_customers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: license_customers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.license_customers_id_seq OWNED BY public.license_customers.id;


--
-- Name: license_payments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.license_payments (
    id integer NOT NULL,
    license_id integer,
    subscription_id integer,
    amount numeric(10,2) NOT NULL,
    currency character varying(3) DEFAULT 'KES'::character varying,
    payment_method character varying(50) DEFAULT 'mpesa'::character varying,
    transaction_id character varying(100),
    mpesa_receipt character varying(50),
    phone_number character varying(20),
    status character varying(20) DEFAULT 'pending'::character varying,
    paid_at timestamp without time zone,
    metadata jsonb DEFAULT '{}'::jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: license_payments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.license_payments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: license_payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.license_payments_id_seq OWNED BY public.license_payments.id;


--
-- Name: license_products; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.license_products (
    id integer NOT NULL,
    code character varying(50) NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    features jsonb DEFAULT '{}'::jsonb,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: license_products_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.license_products_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: license_products_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.license_products_id_seq OWNED BY public.license_products.id;


--
-- Name: license_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.license_subscriptions (
    id integer NOT NULL,
    license_id integer,
    tier_id integer,
    billing_cycle character varying(20) DEFAULT 'monthly'::character varying,
    amount numeric(10,2) NOT NULL,
    currency character varying(3) DEFAULT 'KES'::character varying,
    status character varying(20) DEFAULT 'pending'::character varying,
    next_billing_date date,
    auto_renew boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: license_subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.license_subscriptions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: license_subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.license_subscriptions_id_seq OWNED BY public.license_subscriptions.id;


--
-- Name: license_tiers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.license_tiers (
    id integer NOT NULL,
    product_id integer,
    code character varying(50) NOT NULL,
    name character varying(255) NOT NULL,
    max_users integer DEFAULT 0,
    max_customers integer DEFAULT 0,
    max_onus integer DEFAULT 0,
    features jsonb DEFAULT '{}'::jsonb,
    price_monthly numeric(10,2) DEFAULT 0,
    price_yearly numeric(10,2) DEFAULT 0,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: license_tiers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.license_tiers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: license_tiers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.license_tiers_id_seq OWNED BY public.license_tiers.id;


--
-- Name: license_validation_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.license_validation_logs (
    id integer NOT NULL,
    license_id integer,
    activation_id integer,
    action character varying(50) NOT NULL,
    ip_address character varying(45),
    user_agent text,
    request_data jsonb,
    response_status character varying(20),
    response_message text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: license_validation_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.license_validation_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: license_validation_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.license_validation_logs_id_seq OWNED BY public.license_validation_logs.id;


--
-- Name: licenses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.licenses (
    id integer NOT NULL,
    license_key character varying(64) NOT NULL,
    customer_id integer,
    product_id integer,
    tier_id integer,
    domain_restriction character varying(255),
    max_activations integer DEFAULT 1,
    issued_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    expires_at timestamp without time zone,
    is_active boolean DEFAULT true,
    is_suspended boolean DEFAULT false,
    suspension_reason text,
    notes text,
    metadata jsonb DEFAULT '{}'::jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: licenses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.licenses_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: licenses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.licenses_id_seq OWNED BY public.licenses.id;


--
-- Name: mikrotik_provisioned_ips; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.mikrotik_provisioned_ips (
    id integer NOT NULL,
    subscription_id integer,
    nas_id integer,
    vlan_id integer,
    ip_address character varying(45) NOT NULL,
    mac_address character varying(17),
    dhcp_lease_id character varying(50),
    arp_entry_id character varying(50),
    provision_type character varying(20) DEFAULT 'dhcp_lease'::character varying,
    comment text,
    is_synced boolean DEFAULT false,
    synced_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: mikrotik_provisioned_ips_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.mikrotik_provisioned_ips_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mikrotik_provisioned_ips_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mikrotik_provisioned_ips_id_seq OWNED BY public.mikrotik_provisioned_ips.id;


--
-- Name: mikrotik_vlans; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.mikrotik_vlans (
    id integer NOT NULL,
    nas_id integer,
    name character varying(100) NOT NULL,
    vlan_id integer NOT NULL,
    interface character varying(100) NOT NULL,
    gateway_ip character varying(45),
    network_cidr character varying(45),
    dhcp_pool_start character varying(45),
    dhcp_pool_end character varying(45),
    dhcp_server_name character varying(100),
    dns_servers character varying(255),
    lease_time character varying(20) DEFAULT '1d'::character varying,
    description text,
    is_synced boolean DEFAULT false,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: mikrotik_vlans_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.mikrotik_vlans_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mikrotik_vlans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mikrotik_vlans_id_seq OWNED BY public.mikrotik_vlans.id;


--
-- Name: mobile_notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.mobile_notifications (
    id integer NOT NULL,
    user_id integer NOT NULL,
    type character varying(50) NOT NULL,
    title character varying(255) NOT NULL,
    message text NOT NULL,
    data jsonb DEFAULT '{}'::jsonb,
    is_read boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: mobile_notifications_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.mobile_notifications_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mobile_notifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mobile_notifications_id_seq OWNED BY public.mobile_notifications.id;


--
-- Name: mobile_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.mobile_tokens (
    id integer NOT NULL,
    user_id integer,
    token character varying(64) NOT NULL,
    expires_at timestamp without time zone NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: mobile_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.mobile_tokens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mobile_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mobile_tokens_id_seq OWNED BY public.mobile_tokens.id;


--
-- Name: mpesa_b2b_transactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.mpesa_b2b_transactions (
    id integer NOT NULL,
    request_id character varying(100),
    conversation_id character varying(100),
    originator_conversation_id character varying(100),
    sender_shortcode character varying(20),
    receiver_shortcode character varying(20),
    receiver_type character varying(20) DEFAULT 'paybill'::character varying,
    amount numeric(12,2) NOT NULL,
    currency character varying(3) DEFAULT 'KES'::character varying,
    command_id character varying(50) DEFAULT 'BusinessPayBill'::character varying,
    account_reference character varying(100),
    remarks text,
    status character varying(20) DEFAULT 'pending'::character varying,
    result_code character varying(10),
    result_desc text,
    transaction_id character varying(50),
    debit_party_name character varying(200),
    credit_party_name character varying(200),
    linked_type character varying(50),
    linked_id integer,
    callback_payload jsonb,
    initiated_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    completed_at timestamp without time zone
);


--
-- Name: mpesa_b2b_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.mpesa_b2b_transactions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mpesa_b2b_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mpesa_b2b_transactions_id_seq OWNED BY public.mpesa_b2b_transactions.id;


--
-- Name: mpesa_b2c_transactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.mpesa_b2c_transactions (
    id integer NOT NULL,
    request_id character varying(100),
    conversation_id character varying(100),
    originator_conversation_id character varying(100),
    shortcode character varying(20),
    initiator_name character varying(100),
    phone character varying(20) NOT NULL,
    amount numeric(12,2) NOT NULL,
    currency character varying(3) DEFAULT 'KES'::character varying,
    command_id character varying(50) DEFAULT 'BusinessPayment'::character varying,
    purpose character varying(50) NOT NULL,
    remarks text,
    occasion character varying(100),
    status character varying(20) DEFAULT 'pending'::character varying,
    result_code character varying(10),
    result_desc text,
    transaction_id character varying(50),
    transaction_receipt character varying(100),
    receiver_party_public_name character varying(200),
    b2c_utility_account_balance numeric(12,2),
    b2c_working_account_balance numeric(12,2),
    linked_type character varying(50),
    linked_id integer,
    callback_payload jsonb,
    initiated_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    completed_at timestamp without time zone
);


--
-- Name: mpesa_b2c_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.mpesa_b2c_transactions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mpesa_b2c_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mpesa_b2c_transactions_id_seq OWNED BY public.mpesa_b2c_transactions.id;


--
-- Name: mpesa_c2b_transactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.mpesa_c2b_transactions (
    id integer NOT NULL,
    transaction_type character varying(20),
    trans_id character varying(50),
    trans_time timestamp without time zone,
    trans_amount numeric(12,2),
    business_short_code character varying(20),
    bill_ref_number character varying(100),
    invoice_number character varying(100),
    org_account_balance numeric(12,2),
    third_party_trans_id character varying(100),
    msisdn character varying(20),
    first_name character varying(100),
    middle_name character varying(100),
    last_name character varying(100),
    customer_id integer,
    status character varying(20) DEFAULT 'received'::character varying,
    raw_data jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: mpesa_c2b_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.mpesa_c2b_transactions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mpesa_c2b_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mpesa_c2b_transactions_id_seq OWNED BY public.mpesa_c2b_transactions.id;


--
-- Name: mpesa_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.mpesa_config (
    id integer NOT NULL,
    config_key character varying(50) NOT NULL,
    config_value text,
    is_encrypted boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: mpesa_config_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.mpesa_config_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mpesa_config_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mpesa_config_id_seq OWNED BY public.mpesa_config.id;


--
-- Name: mpesa_transactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.mpesa_transactions (
    id integer NOT NULL,
    transaction_type character varying(20) NOT NULL,
    merchant_request_id character varying(100),
    checkout_request_id character varying(100),
    result_code integer,
    result_desc text,
    mpesa_receipt_number character varying(50),
    transaction_date timestamp without time zone,
    phone_number character varying(20),
    amount numeric(12,2),
    account_reference character varying(100),
    transaction_desc text,
    customer_id integer,
    invoice_id integer,
    status character varying(20) DEFAULT 'pending'::character varying,
    raw_callback jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: mpesa_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.mpesa_transactions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mpesa_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mpesa_transactions_id_seq OWNED BY public.mpesa_transactions.id;


--
-- Name: network_devices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.network_devices (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    device_type character varying(50) DEFAULT 'olt'::character varying NOT NULL,
    vendor character varying(50),
    model character varying(100),
    ip_address character varying(45) NOT NULL,
    snmp_version character varying(10) DEFAULT 'v2c'::character varying,
    snmp_community character varying(100) DEFAULT 'public'::character varying,
    snmp_port integer DEFAULT 161,
    snmpv3_username character varying(100),
    snmpv3_auth_protocol character varying(20),
    snmpv3_auth_password character varying(255),
    snmpv3_priv_protocol character varying(20),
    snmpv3_priv_password character varying(255),
    telnet_username character varying(100),
    telnet_password character varying(255),
    telnet_port integer DEFAULT 23,
    ssh_enabled boolean DEFAULT false,
    ssh_port integer DEFAULT 22,
    location character varying(255),
    status character varying(20) DEFAULT 'unknown'::character varying,
    last_polled timestamp without time zone,
    poll_interval integer DEFAULT 300,
    enabled boolean DEFAULT true,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: network_devices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.network_devices_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: network_devices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.network_devices_id_seq OWNED BY public.network_devices.id;


--
-- Name: onu_discovery_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.onu_discovery_log (
    id integer NOT NULL,
    olt_id integer NOT NULL,
    serial_number character varying(32) NOT NULL,
    frame_slot_port character varying(32),
    first_seen_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    last_seen_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    notified boolean DEFAULT false,
    notified_at timestamp without time zone,
    authorized boolean DEFAULT false,
    authorized_at timestamp without time zone,
    onu_type_id integer,
    equipment_id character varying(100),
    notes text
);


--
-- Name: onu_discovery_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.onu_discovery_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: onu_discovery_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.onu_discovery_log_id_seq OWNED BY public.onu_discovery_log.id;


--
-- Name: onu_fault_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.onu_fault_log (
    id integer NOT NULL,
    olt_id integer,
    onu_id integer,
    serial_number character varying(64),
    prev_status character varying(32),
    new_status character varying(32),
    customer_id integer,
    detected_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    resolved_at timestamp without time zone,
    notified boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: onu_fault_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.onu_fault_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: onu_fault_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.onu_fault_log_id_seq OWNED BY public.onu_fault_log.id;


--
-- Name: onu_signal_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.onu_signal_history (
    id integer NOT NULL,
    onu_id integer,
    rx_power numeric(6,2),
    tx_power numeric(6,2),
    status character varying(20),
    recorded_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: onu_signal_history_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.onu_signal_history_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: onu_signal_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.onu_signal_history_id_seq OWNED BY public.onu_signal_history.id;


--
-- Name: onu_uptime_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.onu_uptime_log (
    id integer NOT NULL,
    onu_id integer,
    status character varying(20) NOT NULL,
    started_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    ended_at timestamp without time zone,
    duration_seconds integer
);


--
-- Name: onu_uptime_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.onu_uptime_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: onu_uptime_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.onu_uptime_log_id_seq OWNED BY public.onu_uptime_log.id;


--
-- Name: orders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.orders (
    id integer NOT NULL,
    order_number character varying(20) NOT NULL,
    package_id integer,
    customer_name character varying(100) NOT NULL,
    customer_email character varying(100),
    customer_phone character varying(20) NOT NULL,
    customer_address text,
    customer_id integer,
    payment_status character varying(20) DEFAULT 'pending'::character varying,
    payment_method character varying(20),
    mpesa_transaction_id integer,
    amount numeric(12,2),
    order_status character varying(20) DEFAULT 'new'::character varying,
    notes text,
    ticket_id integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    salesperson_id integer,
    commission_paid boolean DEFAULT false,
    lead_source character varying(50) DEFAULT 'web'::character varying,
    created_by integer
);


--
-- Name: orders_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.orders_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: orders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.orders_id_seq OWNED BY public.orders.id;


--
-- Name: payroll; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.payroll (
    id integer NOT NULL,
    employee_id integer,
    pay_period_start date NOT NULL,
    pay_period_end date NOT NULL,
    base_salary numeric(12,2) NOT NULL,
    overtime_pay numeric(12,2) DEFAULT 0,
    bonuses numeric(12,2) DEFAULT 0,
    deductions numeric(12,2) DEFAULT 0,
    tax numeric(12,2) DEFAULT 0,
    net_pay numeric(12,2) NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying,
    payment_date date,
    payment_method character varying(50),
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    allowances numeric DEFAULT 0
);


--
-- Name: payroll_commissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.payroll_commissions (
    id integer NOT NULL,
    payroll_id integer,
    employee_id integer,
    commission_type character varying(50) DEFAULT 'ticket'::character varying NOT NULL,
    description text,
    amount numeric(12,2) NOT NULL,
    details jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: payroll_commissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.payroll_commissions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payroll_commissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payroll_commissions_id_seq OWNED BY public.payroll_commissions.id;


--
-- Name: payroll_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.payroll_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payroll_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payroll_id_seq OWNED BY public.payroll.id;


--
-- Name: performance_reviews; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.performance_reviews (
    id integer NOT NULL,
    employee_id integer,
    reviewer_id integer,
    review_period_start date NOT NULL,
    review_period_end date NOT NULL,
    overall_rating integer,
    productivity_rating integer,
    quality_rating integer,
    teamwork_rating integer,
    communication_rating integer,
    goals_achieved text,
    strengths text,
    areas_for_improvement text,
    goals_next_period text,
    comments text,
    status character varying(20) DEFAULT 'draft'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT performance_reviews_communication_rating_check CHECK (((communication_rating >= 1) AND (communication_rating <= 5))),
    CONSTRAINT performance_reviews_overall_rating_check CHECK (((overall_rating >= 1) AND (overall_rating <= 5))),
    CONSTRAINT performance_reviews_productivity_rating_check CHECK (((productivity_rating >= 1) AND (productivity_rating <= 5))),
    CONSTRAINT performance_reviews_quality_rating_check CHECK (((quality_rating >= 1) AND (quality_rating <= 5))),
    CONSTRAINT performance_reviews_teamwork_rating_check CHECK (((teamwork_rating >= 1) AND (teamwork_rating <= 5)))
);


--
-- Name: performance_reviews_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.performance_reviews_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: performance_reviews_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.performance_reviews_id_seq OWNED BY public.performance_reviews.id;


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.permissions (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    display_name character varying(150) NOT NULL,
    category character varying(50) NOT NULL,
    description text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.permissions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: portal_otp; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.portal_otp (
    id integer NOT NULL,
    phone character varying(20) NOT NULL,
    code character varying(6) NOT NULL,
    expires_at timestamp without time zone NOT NULL,
    used boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: portal_otp_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.portal_otp_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: portal_otp_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.portal_otp_id_seq OWNED BY public.portal_otp.id;


--
-- Name: products_services; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.products_services (
    id integer NOT NULL,
    code character varying(50),
    name character varying(200) NOT NULL,
    description text,
    type character varying(20) DEFAULT 'service'::character varying,
    unit_price numeric(12,2) DEFAULT 0 NOT NULL,
    cost_price numeric(12,2) DEFAULT 0,
    tax_rate_id integer,
    income_account_id integer,
    expense_account_id integer,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: products_services_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.products_services_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: products_services_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.products_services_id_seq OWNED BY public.products_services.id;


--
-- Name: public_holidays; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.public_holidays (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    holiday_date date NOT NULL,
    is_recurring boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: public_holidays_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.public_holidays_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: public_holidays_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.public_holidays_id_seq OWNED BY public.public_holidays.id;


--
-- Name: purchase_order_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.purchase_order_items (
    id integer NOT NULL,
    purchase_order_id integer,
    product_id integer,
    equipment_id integer,
    description text NOT NULL,
    quantity numeric(10,2) DEFAULT 1,
    received_quantity numeric(10,2) DEFAULT 0,
    unit_price numeric(12,2) NOT NULL,
    tax_rate_id integer,
    tax_amount numeric(12,2) DEFAULT 0,
    line_total numeric(12,2) NOT NULL,
    sort_order integer DEFAULT 0
);


--
-- Name: purchase_order_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.purchase_order_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_order_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_order_items_id_seq OWNED BY public.purchase_order_items.id;


--
-- Name: purchase_orders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.purchase_orders (
    id integer NOT NULL,
    po_number character varying(50) NOT NULL,
    vendor_id integer,
    order_date date DEFAULT CURRENT_DATE NOT NULL,
    expected_date date,
    status character varying(20) DEFAULT 'draft'::character varying,
    subtotal numeric(12,2) DEFAULT 0,
    tax_amount numeric(12,2) DEFAULT 0,
    total_amount numeric(12,2) DEFAULT 0,
    currency character varying(10) DEFAULT 'KES'::character varying,
    notes text,
    approved_by integer,
    approved_at timestamp without time zone,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: purchase_orders_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.purchase_orders_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_orders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_orders_id_seq OWNED BY public.purchase_orders.id;


--
-- Name: quote_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.quote_items (
    id integer NOT NULL,
    quote_id integer,
    product_id integer,
    description text NOT NULL,
    quantity numeric(10,2) DEFAULT 1,
    unit_price numeric(12,2) NOT NULL,
    tax_rate_id integer,
    tax_amount numeric(12,2) DEFAULT 0,
    discount_percent numeric(5,2) DEFAULT 0,
    line_total numeric(12,2) NOT NULL,
    sort_order integer DEFAULT 0,
    discount numeric(10,2) DEFAULT 0,
    tax_rate numeric(5,2) DEFAULT 0
);


--
-- Name: quote_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.quote_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: quote_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.quote_items_id_seq OWNED BY public.quote_items.id;


--
-- Name: quotes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.quotes (
    id integer NOT NULL,
    quote_number character varying(50) NOT NULL,
    customer_id integer,
    issue_date date DEFAULT CURRENT_DATE NOT NULL,
    expiry_date date,
    status character varying(20) DEFAULT 'draft'::character varying,
    subtotal numeric(12,2) DEFAULT 0,
    tax_amount numeric(12,2) DEFAULT 0,
    discount_amount numeric(12,2) DEFAULT 0,
    total_amount numeric(12,2) DEFAULT 0,
    currency character varying(10) DEFAULT 'KES'::character varying,
    notes text,
    terms text,
    converted_to_invoice_id integer,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: quotes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.quotes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: quotes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.quotes_id_seq OWNED BY public.quotes.id;


--
-- Name: radius_addon_services; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_addon_services (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    price numeric(10,2) DEFAULT 0 NOT NULL,
    billing_type character varying(20) DEFAULT 'monthly'::character varying NOT NULL,
    category character varying(50),
    is_active boolean DEFAULT true,
    setup_fee numeric(10,2) DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    download_speed integer,
    upload_speed integer,
    speed_unit character varying(10) DEFAULT 'Mbps'::character varying
);


--
-- Name: radius_addon_services_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_addon_services_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_addon_services_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_addon_services_id_seq OWNED BY public.radius_addon_services.id;


--
-- Name: radius_alert_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_alert_log (
    id integer NOT NULL,
    subscription_id integer,
    alert_type character varying(30) NOT NULL,
    days_before integer,
    threshold_percent integer,
    message text,
    sent_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: radius_alert_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_alert_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_alert_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_alert_log_id_seq OWNED BY public.radius_alert_log.id;


--
-- Name: radius_auth_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_auth_logs (
    id integer NOT NULL,
    subscription_id integer,
    username character varying(100) NOT NULL,
    nas_ip_address character varying(45),
    mac_address character varying(50),
    auth_result character varying(20) NOT NULL,
    reject_reason character varying(100),
    reply_message text,
    attributes jsonb,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: radius_auth_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_auth_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_auth_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_auth_logs_id_seq OWNED BY public.radius_auth_logs.id;


--
-- Name: radius_billing; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_billing (
    id integer NOT NULL,
    subscription_id integer,
    package_id integer,
    amount numeric(10,2) NOT NULL,
    billing_type character varying(20),
    period_start date,
    period_end date,
    payment_id integer,
    status character varying(20) DEFAULT 'pending'::character varying,
    invoice_number character varying(50),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    payment_method character varying(50),
    transaction_ref character varying(100),
    description text
);


--
-- Name: radius_billing_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_billing_history (
    id integer NOT NULL,
    subscription_id integer NOT NULL,
    transaction_type character varying(50) NOT NULL,
    amount numeric(10,2) NOT NULL,
    description text,
    transaction_ref character varying(100),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: radius_billing_history_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_billing_history_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_billing_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_billing_history_id_seq OWNED BY public.radius_billing_history.id;


--
-- Name: radius_billing_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_billing_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_billing_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_billing_id_seq OWNED BY public.radius_billing.id;


--
-- Name: radius_invoices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_invoices (
    id integer NOT NULL,
    subscription_id integer,
    invoice_number character varying(30) NOT NULL,
    amount numeric(10,2) NOT NULL,
    tax_amount numeric(10,2) DEFAULT 0,
    total_amount numeric(10,2) NOT NULL,
    description text,
    due_date date,
    status character varying(20) DEFAULT 'unpaid'::character varying,
    paid_at timestamp without time zone,
    payment_method character varying(30),
    transaction_ref character varying(100),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: radius_invoices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_invoices_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_invoices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_invoices_id_seq OWNED BY public.radius_invoices.id;


--
-- Name: radius_ip_pools; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_ip_pools (
    id integer NOT NULL,
    pool_name character varying(50) NOT NULL,
    nas_id integer,
    ip_address character varying(45) NOT NULL,
    status character varying(20) DEFAULT 'available'::character varying,
    assigned_to integer,
    assigned_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: radius_ip_pools_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_ip_pools_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_ip_pools_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_ip_pools_id_seq OWNED BY public.radius_ip_pools.id;


--
-- Name: radius_nas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_nas (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    ip_address character varying(45) NOT NULL,
    secret character varying(100) NOT NULL,
    nas_type character varying(50) DEFAULT 'mikrotik'::character varying,
    ports integer DEFAULT 1812,
    description text,
    api_enabled boolean DEFAULT false,
    api_port integer DEFAULT 8728,
    api_username character varying(100),
    api_password_encrypted text,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    wireguard_peer_id integer,
    coa_secret character varying(255),
    location_id integer,
    sub_location_id integer,
    hotspot_portal_url character varying(500),
    hotspot_title character varying(255),
    hotspot_welcome text,
    hotspot_logo_url character varying(500),
    hotspot_theme character varying(50) DEFAULT 'default'::character varying,
    local_ip character varying(255) DEFAULT NULL::character varying
);


--
-- Name: radius_nas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_nas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_nas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_nas_id_seq OWNED BY public.radius_nas.id;


--
-- Name: radius_nas_packages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_nas_packages (
    id integer NOT NULL,
    nas_id integer NOT NULL,
    package_id integer NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: radius_nas_packages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_nas_packages_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_nas_packages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_nas_packages_id_seq OWNED BY public.radius_nas_packages.id;


--
-- Name: radius_package_schedules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_package_schedules (
    id integer NOT NULL,
    package_id integer NOT NULL,
    name character varying(100) NOT NULL,
    start_time time without time zone NOT NULL,
    end_time time without time zone NOT NULL,
    days_of_week character varying(20) DEFAULT '0123456'::character varying,
    download_speed character varying(20) NOT NULL,
    upload_speed character varying(20) NOT NULL,
    priority integer DEFAULT 0,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: radius_package_schedules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_package_schedules_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_package_schedules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_package_schedules_id_seq OWNED BY public.radius_package_schedules.id;


--
-- Name: radius_packages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_packages (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    package_type character varying(20) DEFAULT 'pppoe'::character varying,
    billing_type character varying(20) DEFAULT 'monthly'::character varying,
    price numeric(10,2) DEFAULT 0 NOT NULL,
    validity_days integer DEFAULT 30,
    data_quota_mb bigint,
    download_speed character varying(20),
    upload_speed character varying(20),
    burst_download character varying(20),
    burst_upload character varying(20),
    burst_threshold character varying(20),
    burst_time character varying(20),
    priority integer DEFAULT 8,
    address_pool character varying(100),
    ip_binding boolean DEFAULT false,
    simultaneous_sessions integer DEFAULT 1,
    fup_enabled boolean DEFAULT false,
    fup_quota_mb bigint,
    fup_download_speed character varying(20),
    fup_upload_speed character varying(20),
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    status character varying(20) DEFAULT 'active'::character varying,
    max_devices integer DEFAULT 1,
    session_duration_hours numeric(10,2) DEFAULT NULL::numeric
);


--
-- Name: radius_packages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_packages_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_packages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_packages_id_seq OWNED BY public.radius_packages.id;


--
-- Name: radius_promotions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_promotions (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    package_id integer,
    discount_type character varying(20) DEFAULT 'percent'::character varying,
    discount_value numeric(10,2) NOT NULL,
    promo_code character varying(30),
    start_date date,
    end_date date,
    max_uses integer,
    current_uses integer DEFAULT 0,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: radius_promotions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_promotions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_promotions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_promotions_id_seq OWNED BY public.radius_promotions.id;


--
-- Name: radius_redirect_rules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_redirect_rules (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    condition_type character varying(30) NOT NULL,
    redirect_url text NOT NULL,
    walled_garden text,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: radius_redirect_rules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_redirect_rules_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_redirect_rules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_redirect_rules_id_seq OWNED BY public.radius_redirect_rules.id;


--
-- Name: radius_referrals; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_referrals (
    id integer NOT NULL,
    referrer_subscription_id integer,
    referred_subscription_id integer,
    referral_code character varying(20) NOT NULL,
    reward_type character varying(30) DEFAULT 'days'::character varying,
    reward_value numeric(10,2) DEFAULT 7,
    status character varying(20) DEFAULT 'pending'::character varying,
    rewarded_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: radius_referrals_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_referrals_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_referrals_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_referrals_id_seq OWNED BY public.radius_referrals.id;


--
-- Name: radius_sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_sessions (
    id integer NOT NULL,
    subscription_id integer,
    acct_session_id character varying(64) NOT NULL,
    nas_id integer,
    nas_ip_address character varying(45),
    nas_port_id character varying(50),
    framed_ip_address character varying(45),
    mac_address character varying(17),
    session_start timestamp without time zone NOT NULL,
    session_end timestamp without time zone,
    session_duration integer DEFAULT 0,
    input_octets bigint DEFAULT 0,
    output_octets bigint DEFAULT 0,
    input_packets bigint DEFAULT 0,
    output_packets bigint DEFAULT 0,
    terminate_cause character varying(50),
    status character varying(20) DEFAULT 'active'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    username character varying(100),
    started_at timestamp without time zone,
    stopped_at timestamp without time zone
);


--
-- Name: radius_sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_sessions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_sessions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_sessions_id_seq OWNED BY public.radius_sessions.id;


--
-- Name: radius_speed_overrides; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_speed_overrides (
    id integer NOT NULL,
    package_id integer NOT NULL,
    name character varying(100) NOT NULL,
    download_speed character varying(50) NOT NULL,
    upload_speed character varying(50) NOT NULL,
    start_time time without time zone NOT NULL,
    end_time time without time zone NOT NULL,
    days_of_week character varying(100),
    priority integer DEFAULT 0,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: radius_speed_overrides_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_speed_overrides_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_speed_overrides_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_speed_overrides_id_seq OWNED BY public.radius_speed_overrides.id;


--
-- Name: radius_subscription_addons; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_subscription_addons (
    id integer NOT NULL,
    subscription_id integer NOT NULL,
    addon_id integer NOT NULL,
    quantity integer DEFAULT 1,
    status character varying(20) DEFAULT 'active'::character varying,
    activated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    cancelled_at timestamp without time zone,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    config_data jsonb DEFAULT '{}'::jsonb
);


--
-- Name: radius_subscription_addons_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_subscription_addons_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_subscription_addons_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_subscription_addons_id_seq OWNED BY public.radius_subscription_addons.id;


--
-- Name: radius_subscription_devices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_subscription_devices (
    id integer NOT NULL,
    subscription_id integer NOT NULL,
    mac_address character varying(50) NOT NULL,
    device_name character varying(100),
    last_seen timestamp without time zone,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: radius_subscription_devices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_subscription_devices_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_subscription_devices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_subscription_devices_id_seq OWNED BY public.radius_subscription_devices.id;


--
-- Name: radius_subscription_macs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_subscription_macs (
    id integer NOT NULL,
    subscription_id integer,
    mac_address character varying(17) NOT NULL,
    device_name character varying(100),
    is_primary boolean DEFAULT false,
    auto_captured boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: radius_subscription_macs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_subscription_macs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_subscription_macs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_subscription_macs_id_seq OWNED BY public.radius_subscription_macs.id;


--
-- Name: radius_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_subscriptions (
    id integer NOT NULL,
    customer_id integer,
    package_id integer,
    username character varying(100) NOT NULL,
    password_encrypted text NOT NULL,
    access_type character varying(20) DEFAULT 'pppoe'::character varying,
    static_ip character varying(45),
    mac_address character varying(17),
    status character varying(20) DEFAULT 'active'::character varying,
    start_date date,
    expiry_date date,
    data_used_mb bigint DEFAULT 0,
    last_session_start timestamp without time zone,
    last_session_end timestamp without time zone,
    auto_renew boolean DEFAULT true,
    grace_period_days integer DEFAULT 3,
    notes text,
    nas_id integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    password character varying(100),
    credit_balance numeric(10,2) DEFAULT 0,
    credit_limit numeric(10,2) DEFAULT 0,
    referral_code character varying(20),
    billing_type character varying(20) DEFAULT 'prepaid'::character varying,
    grace_expires_at timestamp without time zone,
    portal_password character varying(255) DEFAULT NULL::character varying,
    location_id integer,
    sub_location_id integer,
    speed_override character varying(50),
    override_expires_at timestamp with time zone,
    suspended_at timestamp without time zone,
    days_remaining_at_suspension integer,
    huawei_onu_id integer
);


--
-- Name: radius_subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_subscriptions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_subscriptions_id_seq OWNED BY public.radius_subscriptions.id;


--
-- Name: radius_usage_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_usage_logs (
    id integer NOT NULL,
    subscription_id integer,
    log_date date NOT NULL,
    download_mb numeric(12,2) DEFAULT 0,
    upload_mb numeric(12,2) DEFAULT 0,
    session_count integer DEFAULT 0,
    session_time_seconds integer DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: radius_usage_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_usage_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_usage_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_usage_logs_id_seq OWNED BY public.radius_usage_logs.id;


--
-- Name: radius_vouchers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radius_vouchers (
    id integer NOT NULL,
    batch_id character varying(50),
    code character varying(20) NOT NULL,
    package_id integer,
    status character varying(20) DEFAULT 'unused'::character varying,
    validity_minutes integer DEFAULT 60,
    data_limit_mb integer,
    used_by_subscription_id integer,
    used_at timestamp without time zone,
    expires_at timestamp without time zone,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: radius_vouchers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radius_vouchers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radius_vouchers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radius_vouchers_id_seq OWNED BY public.radius_vouchers.id;


--
-- Name: radpostauth; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.radpostauth (
    id integer NOT NULL,
    username character varying(100) NOT NULL,
    pass character varying(255),
    reply character varying(32),
    authdate timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    nasipaddress character varying(45),
    calledstationid character varying(50),
    callingstationid character varying(50)
);


--
-- Name: radpostauth_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.radpostauth_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: radpostauth_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.radpostauth_id_seq OWNED BY public.radpostauth.id;


--
-- Name: role_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.role_permissions (
    id integer NOT NULL,
    role_id integer,
    permission_id integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: role_permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.role_permissions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: role_permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.role_permissions_id_seq OWNED BY public.role_permissions.id;


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.roles (
    id integer NOT NULL,
    name character varying(50) NOT NULL,
    display_name character varying(100) NOT NULL,
    description text,
    is_system boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.roles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: salary_advance_repayments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.salary_advance_repayments (
    id integer NOT NULL,
    advance_id integer,
    amount numeric(12,2) NOT NULL,
    repayment_date date NOT NULL,
    payroll_id integer,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: salary_advance_repayments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.salary_advance_repayments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: salary_advance_repayments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.salary_advance_repayments_id_seq OWNED BY public.salary_advance_repayments.id;


--
-- Name: salary_advances; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.salary_advances (
    id integer NOT NULL,
    employee_id integer,
    requested_amount numeric(12,2) NOT NULL,
    approved_amount numeric(12,2),
    repayment_schedule character varying(20) DEFAULT 'monthly'::character varying,
    installments integer DEFAULT 1,
    outstanding_balance numeric(12,2),
    status character varying(20) DEFAULT 'pending'::character varying,
    requested_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    approved_by integer,
    approved_at timestamp without time zone,
    disbursed_at timestamp without time zone,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    mpesa_b2c_transaction_id integer,
    disbursement_status character varying(20) DEFAULT 'pending'::character varying
);


--
-- Name: salary_advances_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.salary_advances_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: salary_advances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.salary_advances_id_seq OWNED BY public.salary_advances.id;


--
-- Name: sales_commissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.sales_commissions (
    id integer NOT NULL,
    salesperson_id integer,
    order_id integer,
    order_amount numeric(12,2) NOT NULL,
    commission_type character varying(20) NOT NULL,
    commission_rate numeric(10,2) NOT NULL,
    commission_amount numeric(12,2) NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying,
    paid_at timestamp without time zone,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: sales_commissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.sales_commissions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sales_commissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sales_commissions_id_seq OWNED BY public.sales_commissions.id;


--
-- Name: salespersons; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.salespersons (
    id integer NOT NULL,
    employee_id integer,
    user_id integer,
    name character varying(100) NOT NULL,
    email character varying(100),
    phone character varying(20) NOT NULL,
    commission_type character varying(20) DEFAULT 'percentage'::character varying,
    commission_value numeric(10,2) DEFAULT 0,
    total_sales numeric(12,2) DEFAULT 0,
    total_commission numeric(12,2) DEFAULT 0,
    is_active boolean DEFAULT true,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: salespersons_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.salespersons_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: salespersons_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.salespersons_id_seq OWNED BY public.salespersons.id;


--
-- Name: schema_migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.schema_migrations (
    id integer NOT NULL,
    version character varying(50) NOT NULL,
    applied_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: schema_migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.schema_migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: schema_migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.schema_migrations_id_seq OWNED BY public.schema_migrations.id;


--
-- Name: service_fee_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.service_fee_types (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    default_amount numeric(12,2) DEFAULT 0,
    currency character varying(10) DEFAULT 'KES'::character varying,
    is_active boolean DEFAULT true,
    display_order integer DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: service_fee_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.service_fee_types_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: service_fee_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.service_fee_types_id_seq OWNED BY public.service_fee_types.id;


--
-- Name: service_packages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.service_packages (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    slug character varying(100) NOT NULL,
    description text,
    speed character varying(50) NOT NULL,
    speed_unit character varying(10) DEFAULT 'Mbps'::character varying,
    price numeric(10,2) NOT NULL,
    currency character varying(10) DEFAULT 'KES'::character varying,
    billing_cycle character varying(20) DEFAULT 'monthly'::character varying,
    features jsonb DEFAULT '[]'::jsonb,
    is_popular boolean DEFAULT false,
    is_active boolean DEFAULT true,
    display_order integer DEFAULT 0,
    badge_text character varying(50),
    badge_color character varying(20),
    icon character varying(50) DEFAULT 'wifi'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: service_packages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.service_packages_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: service_packages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.service_packages_id_seq OWNED BY public.service_packages.id;


--
-- Name: settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.settings (
    id integer NOT NULL,
    setting_key character varying(100) NOT NULL,
    setting_value text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    setting_group character varying(50)
);


--
-- Name: settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.settings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.settings_id_seq OWNED BY public.settings.id;


--
-- Name: sla_business_hours; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.sla_business_hours (
    id integer NOT NULL,
    day_of_week integer NOT NULL,
    start_time time without time zone DEFAULT '08:00:00'::time without time zone NOT NULL,
    end_time time without time zone DEFAULT '17:00:00'::time without time zone NOT NULL,
    is_working_day boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT sla_business_hours_day_of_week_check CHECK (((day_of_week >= 0) AND (day_of_week <= 6)))
);


--
-- Name: sla_business_hours_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.sla_business_hours_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sla_business_hours_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sla_business_hours_id_seq OWNED BY public.sla_business_hours.id;


--
-- Name: sla_holidays; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.sla_holidays (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    holiday_date date NOT NULL,
    is_recurring boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: sla_holidays_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.sla_holidays_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sla_holidays_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sla_holidays_id_seq OWNED BY public.sla_holidays.id;


--
-- Name: sla_policies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.sla_policies (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    priority character varying(20) NOT NULL,
    response_time_hours integer DEFAULT 4 NOT NULL,
    resolution_time_hours integer DEFAULT 24 NOT NULL,
    escalation_time_hours integer,
    escalation_to integer,
    notify_on_breach boolean DEFAULT true,
    is_active boolean DEFAULT true,
    is_default boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: sla_policies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.sla_policies_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sla_policies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sla_policies_id_seq OWNED BY public.sla_policies.id;


--
-- Name: sms_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.sms_logs (
    id integer NOT NULL,
    ticket_id integer,
    recipient_phone character varying(20) NOT NULL,
    recipient_type character varying(20) NOT NULL,
    message text NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying,
    sent_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: sms_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.sms_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sms_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sms_logs_id_seq OWNED BY public.sms_logs.id;


--
-- Name: tax_rates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.tax_rates (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    rate numeric(5,2) DEFAULT 16.00 NOT NULL,
    type character varying(20) DEFAULT 'percentage'::character varying,
    is_inclusive boolean DEFAULT false,
    is_default boolean DEFAULT false,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: tax_rates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.tax_rates_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tax_rates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tax_rates_id_seq OWNED BY public.tax_rates.id;


--
-- Name: team_members; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.team_members (
    id integer NOT NULL,
    team_id integer NOT NULL,
    employee_id integer NOT NULL,
    joined_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: team_members_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.team_members_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: team_members_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.team_members_id_seq OWNED BY public.team_members.id;


--
-- Name: teams; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.teams (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    leader_id integer,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    branch_id integer
);


--
-- Name: teams_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.teams_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: teams_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.teams_id_seq OWNED BY public.teams.id;


--
-- Name: technician_kit_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.technician_kit_items (
    id integer NOT NULL,
    kit_id integer,
    equipment_id integer,
    category_id integer,
    quantity integer DEFAULT 1,
    issued_quantity integer DEFAULT 0,
    returned_quantity integer DEFAULT 0,
    status character varying(20) DEFAULT 'issued'::character varying,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: technician_kit_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.technician_kit_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: technician_kit_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.technician_kit_items_id_seq OWNED BY public.technician_kit_items.id;


--
-- Name: technician_kits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.technician_kits (
    id integer NOT NULL,
    kit_number character varying(30) NOT NULL,
    employee_id integer,
    name character varying(100) NOT NULL,
    description text,
    status character varying(20) DEFAULT 'active'::character varying,
    issued_date date,
    issued_by integer,
    returned_date date,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: technician_kits_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.technician_kits_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: technician_kits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.technician_kits_id_seq OWNED BY public.technician_kits.id;


--
-- Name: ticket_categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.ticket_categories (
    id integer NOT NULL,
    key character varying(50) NOT NULL,
    label character varying(100) NOT NULL,
    description text,
    color character varying(20) DEFAULT 'primary'::character varying,
    display_order integer DEFAULT 0,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: ticket_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.ticket_categories_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_categories_id_seq OWNED BY public.ticket_categories.id;


--
-- Name: ticket_comments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.ticket_comments (
    id integer NOT NULL,
    ticket_id integer,
    user_id integer,
    comment text NOT NULL,
    is_internal boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: ticket_comments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.ticket_comments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_comments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_comments_id_seq OWNED BY public.ticket_comments.id;


--
-- Name: ticket_commission_rates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.ticket_commission_rates (
    id integer NOT NULL,
    category character varying(50) NOT NULL,
    rate numeric(12,2) DEFAULT 0 NOT NULL,
    currency character varying(10) DEFAULT 'KES'::character varying,
    description text,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    require_sla_compliance boolean DEFAULT false
);


--
-- Name: ticket_commission_rates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.ticket_commission_rates_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_commission_rates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_commission_rates_id_seq OWNED BY public.ticket_commission_rates.id;


--
-- Name: ticket_earnings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.ticket_earnings (
    id integer NOT NULL,
    ticket_id integer,
    employee_id integer,
    team_id integer,
    category character varying(50) NOT NULL,
    full_rate numeric(12,2) NOT NULL,
    earned_amount numeric(12,2) NOT NULL,
    share_count integer DEFAULT 1,
    currency character varying(10) DEFAULT 'KES'::character varying,
    status character varying(20) DEFAULT 'pending'::character varying,
    payroll_id integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    sla_compliant boolean DEFAULT true,
    sla_note text
);


--
-- Name: ticket_earnings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.ticket_earnings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_earnings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_earnings_id_seq OWNED BY public.ticket_earnings.id;


--
-- Name: ticket_escalations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.ticket_escalations (
    id integer NOT NULL,
    ticket_id integer,
    escalated_by integer,
    escalated_to integer,
    reason text NOT NULL,
    previous_priority character varying(20),
    new_priority character varying(20),
    previous_assigned_to integer,
    status character varying(20) DEFAULT 'active'::character varying,
    resolved_at timestamp without time zone,
    resolution_notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: ticket_escalations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.ticket_escalations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_escalations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_escalations_id_seq OWNED BY public.ticket_escalations.id;


--
-- Name: ticket_resolution_photos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.ticket_resolution_photos (
    id integer NOT NULL,
    ticket_id integer,
    resolution_id integer,
    photo_type character varying(50) NOT NULL,
    file_path character varying(500) NOT NULL,
    file_name character varying(255),
    caption text,
    uploaded_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: ticket_resolution_photos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.ticket_resolution_photos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_resolution_photos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_resolution_photos_id_seq OWNED BY public.ticket_resolution_photos.id;


--
-- Name: ticket_resolutions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.ticket_resolutions (
    id integer NOT NULL,
    ticket_id integer,
    resolved_by integer,
    resolution_notes text,
    router_serial character varying(100),
    power_levels character varying(100),
    cable_used character varying(100),
    equipment_installed text,
    additional_notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: ticket_resolutions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.ticket_resolutions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_resolutions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_resolutions_id_seq OWNED BY public.ticket_resolutions.id;


--
-- Name: ticket_satisfaction_ratings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.ticket_satisfaction_ratings (
    id integer NOT NULL,
    ticket_id integer,
    customer_id integer,
    rating integer NOT NULL,
    feedback text,
    rated_by_name character varying(100),
    rated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ticket_satisfaction_ratings_rating_check CHECK (((rating >= 1) AND (rating <= 5)))
);


--
-- Name: ticket_satisfaction_ratings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.ticket_satisfaction_ratings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_satisfaction_ratings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_satisfaction_ratings_id_seq OWNED BY public.ticket_satisfaction_ratings.id;


--
-- Name: ticket_service_fees; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.ticket_service_fees (
    id integer NOT NULL,
    ticket_id integer NOT NULL,
    fee_type_id integer,
    fee_name character varying(100) NOT NULL,
    amount numeric(12,2) DEFAULT 0 NOT NULL,
    currency character varying(10) DEFAULT 'KES'::character varying,
    notes text,
    is_paid boolean DEFAULT false,
    paid_at timestamp without time zone,
    payment_reference character varying(100),
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: ticket_service_fees_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.ticket_service_fees_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_service_fees_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_service_fees_id_seq OWNED BY public.ticket_service_fees.id;


--
-- Name: ticket_sla_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.ticket_sla_logs (
    id integer NOT NULL,
    ticket_id integer,
    event_type character varying(50) NOT NULL,
    details text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: ticket_sla_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.ticket_sla_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_sla_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_sla_logs_id_seq OWNED BY public.ticket_sla_logs.id;


--
-- Name: ticket_status_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.ticket_status_tokens (
    id integer NOT NULL,
    ticket_id integer NOT NULL,
    employee_id integer,
    token_hash character varying(255) NOT NULL,
    allowed_statuses text DEFAULT 'In Progress,Resolved,Closed'::text,
    expires_at timestamp without time zone NOT NULL,
    max_uses integer DEFAULT 10,
    used_count integer DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    last_used_at timestamp without time zone,
    is_active boolean DEFAULT true,
    token_lookup character varying(32)
);


--
-- Name: ticket_status_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.ticket_status_tokens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_status_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_status_tokens_id_seq OWNED BY public.ticket_status_tokens.id;


--
-- Name: ticket_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.ticket_templates (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    category character varying(50),
    subject character varying(200),
    content text NOT NULL,
    is_active boolean DEFAULT true,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: ticket_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.ticket_templates_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_templates_id_seq OWNED BY public.ticket_templates.id;


--
-- Name: tickets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.tickets (
    id integer NOT NULL,
    ticket_number character varying(20) NOT NULL,
    customer_id integer,
    assigned_to integer,
    subject character varying(200) NOT NULL,
    description text NOT NULL,
    category character varying(50) NOT NULL,
    priority character varying(20) DEFAULT 'medium'::character varying,
    status character varying(20) DEFAULT 'open'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    resolved_at timestamp without time zone,
    team_id integer,
    sla_policy_id integer,
    first_response_at timestamp without time zone,
    sla_response_due timestamp without time zone,
    sla_resolution_due timestamp without time zone,
    sla_response_breached boolean DEFAULT false,
    sla_resolution_breached boolean DEFAULT false,
    sla_paused_at timestamp without time zone,
    sla_paused_duration integer DEFAULT 0,
    source character varying(50) DEFAULT 'internal'::character varying,
    created_by integer,
    is_escalated boolean DEFAULT false,
    escalation_count integer DEFAULT 0,
    satisfaction_rating integer,
    closed_at timestamp without time zone,
    branch_id integer
);


--
-- Name: tickets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.tickets_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tickets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tickets_id_seq OWNED BY public.tickets.id;


--
-- Name: tr069_devices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.tr069_devices (
    id integer NOT NULL,
    onu_id integer,
    device_id character varying(255),
    serial_number character varying(64),
    manufacturer character varying(64),
    model character varying(64),
    last_inform timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone,
    ip_address character varying(45),
    provision_state character varying(20) DEFAULT 'NEW'::character varying,
    last_provision_at timestamp without time zone
);


--
-- Name: tr069_devices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.tr069_devices_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tr069_devices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tr069_devices_id_seq OWNED BY public.tr069_devices.id;


--
-- Name: user_notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.user_notifications (
    id integer NOT NULL,
    user_id integer,
    type character varying(50) DEFAULT 'info'::character varying NOT NULL,
    title character varying(255) NOT NULL,
    message text,
    reference_id integer,
    is_read boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    link character varying(500)
);


--
-- Name: user_notifications_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.user_notifications_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_notifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_notifications_id_seq OWNED BY public.user_notifications.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.users (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    email character varying(100) NOT NULL,
    phone character varying(20) NOT NULL,
    role character varying(20) DEFAULT 'technician'::character varying NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    password_hash character varying(255),
    role_id integer
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.users_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: vendor_bill_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.vendor_bill_items (
    id integer NOT NULL,
    bill_id integer,
    account_id integer,
    description text NOT NULL,
    quantity numeric(10,2) DEFAULT 1,
    unit_price numeric(12,2) NOT NULL,
    tax_rate_id integer,
    tax_amount numeric(12,2) DEFAULT 0,
    line_total numeric(12,2) NOT NULL,
    sort_order integer DEFAULT 0
);


--
-- Name: vendor_bill_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.vendor_bill_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vendor_bill_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vendor_bill_items_id_seq OWNED BY public.vendor_bill_items.id;


--
-- Name: vendor_bills; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.vendor_bills (
    id integer NOT NULL,
    bill_number character varying(50) NOT NULL,
    vendor_id integer,
    purchase_order_id integer,
    bill_date date DEFAULT CURRENT_DATE NOT NULL,
    due_date date NOT NULL,
    status character varying(20) DEFAULT 'unpaid'::character varying,
    subtotal numeric(12,2) DEFAULT 0,
    tax_amount numeric(12,2) DEFAULT 0,
    total_amount numeric(12,2) DEFAULT 0,
    amount_paid numeric(12,2) DEFAULT 0,
    balance_due numeric(12,2) DEFAULT 0,
    currency character varying(10) DEFAULT 'KES'::character varying,
    reference character varying(100),
    notes text,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    reminder_enabled boolean DEFAULT false,
    reminder_days_before integer DEFAULT 3,
    last_reminder_sent timestamp without time zone,
    reminder_count integer DEFAULT 0
);


--
-- Name: vendor_bills_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.vendor_bills_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vendor_bills_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vendor_bills_id_seq OWNED BY public.vendor_bills.id;


--
-- Name: vendor_payments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.vendor_payments (
    id integer NOT NULL,
    payment_number character varying(50) NOT NULL,
    vendor_id integer,
    bill_id integer,
    payment_date date DEFAULT CURRENT_DATE NOT NULL,
    amount numeric(12,2) NOT NULL,
    payment_method character varying(50) NOT NULL,
    reference character varying(100),
    notes text,
    status character varying(20) DEFAULT 'completed'::character varying,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: vendor_payments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.vendor_payments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vendor_payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vendor_payments_id_seq OWNED BY public.vendor_payments.id;


--
-- Name: vendors; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.vendors (
    id integer NOT NULL,
    name character varying(200) NOT NULL,
    contact_person character varying(100),
    email character varying(100),
    phone character varying(50),
    address text,
    city character varying(100),
    country character varying(100) DEFAULT 'Kenya'::character varying,
    tax_pin character varying(50),
    payment_terms integer DEFAULT 30,
    currency character varying(10) DEFAULT 'KES'::character varying,
    notes text,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: vendors_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.vendors_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vendors_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vendors_id_seq OWNED BY public.vendors.id;


--
-- Name: vlan_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.vlan_history (
    id integer NOT NULL,
    vlan_record_id integer,
    in_octets bigint DEFAULT 0,
    out_octets bigint DEFAULT 0,
    in_rate bigint DEFAULT 0,
    out_rate bigint DEFAULT 0,
    recorded_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: vlan_history_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.vlan_history_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vlan_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vlan_history_id_seq OWNED BY public.vlan_history.id;


--
-- Name: vlan_traffic_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.vlan_traffic_history (
    id integer NOT NULL,
    vlan_id integer,
    rx_bytes bigint DEFAULT 0 NOT NULL,
    tx_bytes bigint DEFAULT 0 NOT NULL,
    rx_packets bigint DEFAULT 0 NOT NULL,
    tx_packets bigint DEFAULT 0 NOT NULL,
    rx_rate numeric(12,2) DEFAULT 0,
    tx_rate numeric(12,2) DEFAULT 0,
    is_running boolean DEFAULT false,
    recorded_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: vlan_traffic_history_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.vlan_traffic_history_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vlan_traffic_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vlan_traffic_history_id_seq OWNED BY public.vlan_traffic_history.id;


--
-- Name: whatsapp_conversations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.whatsapp_conversations (
    id integer NOT NULL,
    chat_id character varying(100) NOT NULL,
    phone character varying(30) NOT NULL,
    contact_name character varying(150),
    customer_id integer,
    is_group boolean DEFAULT false,
    unread_count integer DEFAULT 0,
    last_message_at timestamp without time zone,
    last_message_preview text,
    status character varying(20) DEFAULT 'active'::character varying,
    assigned_to integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: whatsapp_conversations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.whatsapp_conversations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: whatsapp_conversations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.whatsapp_conversations_id_seq OWNED BY public.whatsapp_conversations.id;


--
-- Name: whatsapp_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.whatsapp_logs (
    id integer NOT NULL,
    ticket_id integer,
    recipient_phone character varying(20) NOT NULL,
    recipient_type character varying(20) NOT NULL,
    message text,
    status character varying(20) DEFAULT 'pending'::character varying,
    sent_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    order_id integer,
    complaint_id integer,
    message_type character varying(50) DEFAULT 'custom'::character varying
);


--
-- Name: whatsapp_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.whatsapp_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: whatsapp_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.whatsapp_logs_id_seq OWNED BY public.whatsapp_logs.id;


--
-- Name: whatsapp_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.whatsapp_messages (
    id integer NOT NULL,
    conversation_id integer,
    message_id character varying(150),
    direction character varying(10) DEFAULT 'incoming'::character varying NOT NULL,
    sender_phone character varying(30),
    sender_name character varying(150),
    message_type character varying(30) DEFAULT 'text'::character varying,
    body text,
    media_url text,
    media_mime_type character varying(100),
    media_filename character varying(255),
    is_read boolean DEFAULT false,
    is_delivered boolean DEFAULT false,
    sent_by integer,
    "timestamp" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    raw_data jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: whatsapp_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.whatsapp_messages_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: whatsapp_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.whatsapp_messages_id_seq OWNED BY public.whatsapp_messages.id;


--
-- Name: wireguard_peers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.wireguard_peers (
    id integer NOT NULL,
    server_id integer,
    name character varying(100) NOT NULL,
    description text,
    public_key text NOT NULL,
    private_key_encrypted text,
    preshared_key_encrypted text,
    allowed_ips text NOT NULL,
    endpoint character varying(255),
    persistent_keepalive integer DEFAULT 25,
    last_handshake_at timestamp without time zone,
    rx_bytes bigint DEFAULT 0,
    tx_bytes bigint DEFAULT 0,
    is_active boolean DEFAULT true,
    is_olt_site boolean DEFAULT false,
    olt_id integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: wireguard_peers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.wireguard_peers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wireguard_peers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wireguard_peers_id_seq OWNED BY public.wireguard_peers.id;


--
-- Name: wireguard_servers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.wireguard_servers (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    enabled boolean DEFAULT true,
    interface_name character varying(20) DEFAULT 'wg0'::character varying,
    interface_addr character varying(50) NOT NULL,
    listen_port integer DEFAULT 51820,
    public_key text,
    private_key_encrypted text,
    preshared_key_encrypted text,
    mtu integer DEFAULT 1420,
    dns_servers character varying(255),
    post_up_cmd text,
    post_down_cmd text,
    health_status character varying(50) DEFAULT 'unknown'::character varying,
    last_handshake_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: wireguard_servers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.wireguard_servers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wireguard_servers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wireguard_servers_id_seq OWNED BY public.wireguard_servers.id;


--
-- Name: wireguard_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.wireguard_settings (
    id integer NOT NULL,
    setting_key character varying(100) NOT NULL,
    setting_value text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: wireguard_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.wireguard_settings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wireguard_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wireguard_settings_id_seq OWNED BY public.wireguard_settings.id;


--
-- Name: wireguard_subnets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.wireguard_subnets (
    id integer NOT NULL,
    vpn_peer_id integer,
    network_cidr character varying(50) NOT NULL,
    description character varying(255),
    subnet_type character varying(50) DEFAULT 'management'::character varying,
    is_olt_management boolean DEFAULT false,
    is_tr069_range boolean DEFAULT false,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: wireguard_subnets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.wireguard_subnets_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wireguard_subnets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wireguard_subnets_id_seq OWNED BY public.wireguard_subnets.id;


--
-- Name: wireguard_sync_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE IF NOT EXISTS public.wireguard_sync_logs (
    id integer NOT NULL,
    server_id integer,
    success boolean DEFAULT false,
    message text,
    synced_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: wireguard_sync_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE IF NOT EXISTS public.wireguard_sync_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wireguard_sync_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wireguard_sync_logs_id_seq OWNED BY public.wireguard_sync_logs.id;


--
-- Name: absent_deduction_rules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.absent_deduction_rules ALTER COLUMN id SET DEFAULT nextval('public.absent_deduction_rules_id_seq'::regclass);


--
-- Name: accounting_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accounting_settings ALTER COLUMN id SET DEFAULT nextval('public.accounting_settings_id_seq'::regclass);


--
-- Name: activity_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_logs ALTER COLUMN id SET DEFAULT nextval('public.activity_logs_id_seq'::regclass);


--
-- Name: announcement_recipients id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.announcement_recipients ALTER COLUMN id SET DEFAULT nextval('public.announcement_recipients_id_seq'::regclass);


--
-- Name: announcements id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.announcements ALTER COLUMN id SET DEFAULT nextval('public.announcements_id_seq'::regclass);


--
-- Name: attendance id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance ALTER COLUMN id SET DEFAULT nextval('public.attendance_id_seq'::regclass);


--
-- Name: attendance_notification_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendance_notification_logs ALTER COLUMN id SET DEFAULT nextval('public.attendance_notification_logs_id_seq'::regclass);


--
-- Name: bill_reminders id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bill_reminders ALTER COLUMN id SET DEFAULT nextval('public.bill_reminders_id_seq'::regclass);


--
-- Name: biometric_attendance_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.biometric_attendance_logs ALTER COLUMN id SET DEFAULT nextval('public.biometric_attendance_logs_id_seq'::regclass);


--
-- Name: biometric_devices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.biometric_devices ALTER COLUMN id SET DEFAULT nextval('public.biometric_devices_id_seq'::regclass);


--
-- Name: branch_employees id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branch_employees ALTER COLUMN id SET DEFAULT nextval('public.branch_employees_id_seq'::regclass);


--
-- Name: branches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branches ALTER COLUMN id SET DEFAULT nextval('public.branches_id_seq'::regclass);


--
-- Name: call_center_agent_status id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.call_center_agent_status ALTER COLUMN id SET DEFAULT nextval('public.call_center_agent_status_id_seq'::regclass);


--
-- Name: call_center_calls id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.call_center_calls ALTER COLUMN id SET DEFAULT nextval('public.call_center_calls_id_seq'::regclass);


--
-- Name: call_center_extensions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.call_center_extensions ALTER COLUMN id SET DEFAULT nextval('public.call_center_extensions_id_seq'::regclass);


--
-- Name: call_center_inbound_routes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.call_center_inbound_routes ALTER COLUMN id SET DEFAULT nextval('public.call_center_inbound_routes_id_seq'::regclass);


--
-- Name: call_center_ivr id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.call_center_ivr ALTER COLUMN id SET DEFAULT nextval('public.call_center_ivr_id_seq'::regclass);


--
-- Name: call_center_ivr_options id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.call_center_ivr_options ALTER COLUMN id SET DEFAULT nextval('public.call_center_ivr_options_id_seq'::regclass);


--
-- Name: call_center_outbound_routes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.call_center_outbound_routes ALTER COLUMN id SET DEFAULT nextval('public.call_center_outbound_routes_id_seq'::regclass);


--
-- Name: call_center_queue_members id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.call_center_queue_members ALTER COLUMN id SET DEFAULT nextval('public.call_center_queue_members_id_seq'::regclass);


--
-- Name: call_center_queues id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.call_center_queues ALTER COLUMN id SET DEFAULT nextval('public.call_center_queues_id_seq'::regclass);


--
-- Name: call_center_ring_group_members id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.call_center_ring_group_members ALTER COLUMN id SET DEFAULT nextval('public.call_center_ring_group_members_id_seq'::regclass);


--
-- Name: call_center_ring_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.call_center_ring_groups ALTER COLUMN id SET DEFAULT nextval('public.call_center_ring_groups_id_seq'::regclass);


--
-- Name: call_center_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.call_center_settings ALTER COLUMN id SET DEFAULT nextval('public.call_center_settings_id_seq'::regclass);


--
-- Name: call_center_time_conditions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.call_center_time_conditions ALTER COLUMN id SET DEFAULT nextval('public.call_center_time_conditions_id_seq'::regclass);


--
-- Name: call_center_trunks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.call_center_trunks ALTER COLUMN id SET DEFAULT nextval('public.call_center_trunks_id_seq'::regclass);


--
-- Name: chart_of_accounts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chart_of_accounts ALTER COLUMN id SET DEFAULT nextval('public.chart_of_accounts_id_seq'::regclass);


--
-- Name: company_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_settings ALTER COLUMN id SET DEFAULT nextval('public.company_settings_id_seq'::regclass);


--
-- Name: complaints id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.complaints ALTER COLUMN id SET DEFAULT nextval('public.complaints_id_seq'::regclass);


--
-- Name: customer_payments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_payments ALTER COLUMN id SET DEFAULT nextval('public.customer_payments_id_seq'::regclass);


--
-- Name: customer_ticket_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_ticket_tokens ALTER COLUMN id SET DEFAULT nextval('public.customer_ticket_tokens_id_seq'::regclass);


--
-- Name: customers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers ALTER COLUMN id SET DEFAULT nextval('public.customers_id_seq'::regclass);


--
-- Name: departments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments ALTER COLUMN id SET DEFAULT nextval('public.departments_id_seq'::regclass);


--
-- Name: device_interfaces id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.device_interfaces ALTER COLUMN id SET DEFAULT nextval('public.device_interfaces_id_seq'::regclass);


--
-- Name: device_monitoring_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.device_monitoring_log ALTER COLUMN id SET DEFAULT nextval('public.device_monitoring_log_id_seq'::regclass);


--
-- Name: device_onus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.device_onus ALTER COLUMN id SET DEFAULT nextval('public.device_onus_id_seq'::regclass);


--
-- Name: device_user_mapping id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.device_user_mapping ALTER COLUMN id SET DEFAULT nextval('public.device_user_mapping_id_seq'::regclass);


--
-- Name: device_vlans id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.device_vlans ALTER COLUMN id SET DEFAULT nextval('public.device_vlans_id_seq'::regclass);


--
-- Name: email_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_logs ALTER COLUMN id SET DEFAULT nextval('public.email_logs_id_seq'::regclass);


--
-- Name: employee_branches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_branches ALTER COLUMN id SET DEFAULT nextval('public.employee_branches_id_seq'::regclass);


--
-- Name: employee_kyc_documents id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employee_kyc_documents ALTER COLUMN id SET DEFAULT nextval('public.employee_kyc_documents_id_seq'::regclass);


--
-- Name: employees id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees ALTER COLUMN id SET DEFAULT nextval('public.employees_id_seq'::regclass);


--
-- Name: equipment id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment ALTER COLUMN id SET DEFAULT nextval('public.equipment_id_seq'::regclass);


--
-- Name: equipment_assignments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_assignments ALTER COLUMN id SET DEFAULT nextval('public.equipment_assignments_id_seq'::regclass);


--
-- Name: equipment_categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_categories ALTER COLUMN id SET DEFAULT nextval('public.equipment_categories_id_seq'::regclass);


--
-- Name: equipment_faults id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_faults ALTER COLUMN id SET DEFAULT nextval('public.equipment_faults_id_seq'::regclass);


--
-- Name: equipment_lifecycle_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_lifecycle_logs ALTER COLUMN id SET DEFAULT nextval('public.equipment_lifecycle_logs_id_seq'::regclass);


--
-- Name: equipment_loans id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_loans ALTER COLUMN id SET DEFAULT nextval('public.equipment_loans_id_seq'::regclass);


--
-- Name: expense_categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expense_categories ALTER COLUMN id SET DEFAULT nextval('public.expense_categories_id_seq'::regclass);


--
-- Name: expenses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expenses ALTER COLUMN id SET DEFAULT nextval('public.expenses_id_seq'::regclass);


--
-- Name: genieacs_config id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.genieacs_config ALTER COLUMN id SET DEFAULT nextval('public.genieacs_config_id_seq'::regclass);


--
-- Name: hr_notification_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hr_notification_templates ALTER COLUMN id SET DEFAULT nextval('public.hr_notification_templates_id_seq'::regclass);


--
-- Name: huawei_alerts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_alerts ALTER COLUMN id SET DEFAULT nextval('public.huawei_alerts_id_seq'::regclass);


--
-- Name: huawei_apartments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_apartments ALTER COLUMN id SET DEFAULT nextval('public.huawei_apartments_id_seq'::regclass);


--
-- Name: huawei_boards id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_boards ALTER COLUMN id SET DEFAULT nextval('public.huawei_boards_id_seq'::regclass);


--
-- Name: huawei_dba_profiles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_dba_profiles ALTER COLUMN id SET DEFAULT nextval('public.huawei_dba_profiles_id_seq'::regclass);


--
-- Name: huawei_odb_units id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_odb_units ALTER COLUMN id SET DEFAULT nextval('public.huawei_odb_units_id_seq'::regclass);


--
-- Name: huawei_olt_boards id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_olt_boards ALTER COLUMN id SET DEFAULT nextval('public.huawei_olt_boards_id_seq'::regclass);


--
-- Name: huawei_olt_line_profiles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_olt_line_profiles ALTER COLUMN id SET DEFAULT nextval('public.huawei_olt_line_profiles_id_seq'::regclass);


--
-- Name: huawei_olt_pon_ports id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_olt_pon_ports ALTER COLUMN id SET DEFAULT nextval('public.huawei_olt_pon_ports_id_seq'::regclass);


--
-- Name: huawei_olt_srv_profiles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_olt_srv_profiles ALTER COLUMN id SET DEFAULT nextval('public.huawei_olt_srv_profiles_id_seq'::regclass);


--
-- Name: huawei_olt_uplinks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_olt_uplinks ALTER COLUMN id SET DEFAULT nextval('public.huawei_olt_uplinks_id_seq'::regclass);


--
-- Name: huawei_olt_vlans id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_olt_vlans ALTER COLUMN id SET DEFAULT nextval('public.huawei_olt_vlans_id_seq'::regclass);


--
-- Name: huawei_olts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_olts ALTER COLUMN id SET DEFAULT nextval('public.huawei_olts_id_seq'::regclass);


--
-- Name: huawei_onu_mgmt_ips id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_onu_mgmt_ips ALTER COLUMN id SET DEFAULT nextval('public.huawei_onu_mgmt_ips_id_seq'::regclass);


--
-- Name: huawei_onu_service_vlans id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_onu_service_vlans ALTER COLUMN id SET DEFAULT nextval('public.huawei_onu_service_vlans_id_seq'::regclass);


--
-- Name: huawei_onu_tr069_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_onu_tr069_logs ALTER COLUMN id SET DEFAULT nextval('public.huawei_onu_tr069_logs_id_seq'::regclass);


--
-- Name: huawei_onu_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_onu_types ALTER COLUMN id SET DEFAULT nextval('public.huawei_onu_types_id_seq'::regclass);


--
-- Name: huawei_onus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_onus ALTER COLUMN id SET DEFAULT nextval('public.huawei_onus_id_seq'::regclass);


--
-- Name: huawei_pon_ports id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_pon_ports ALTER COLUMN id SET DEFAULT nextval('public.huawei_pon_ports_id_seq'::regclass);


--
-- Name: huawei_port_vlans id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_port_vlans ALTER COLUMN id SET DEFAULT nextval('public.huawei_port_vlans_id_seq'::regclass);


--
-- Name: huawei_provisioning_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_provisioning_logs ALTER COLUMN id SET DEFAULT nextval('public.huawei_provisioning_logs_id_seq'::regclass);


--
-- Name: huawei_service_profiles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_service_profiles ALTER COLUMN id SET DEFAULT nextval('public.huawei_service_profiles_id_seq'::regclass);


--
-- Name: huawei_service_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_service_templates ALTER COLUMN id SET DEFAULT nextval('public.huawei_service_templates_id_seq'::regclass);


--
-- Name: huawei_subzones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_subzones ALTER COLUMN id SET DEFAULT nextval('public.huawei_subzones_id_seq'::regclass);


--
-- Name: huawei_uplinks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_uplinks ALTER COLUMN id SET DEFAULT nextval('public.huawei_uplinks_id_seq'::regclass);


--
-- Name: huawei_vlans id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_vlans ALTER COLUMN id SET DEFAULT nextval('public.huawei_vlans_id_seq'::regclass);


--
-- Name: huawei_zones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.huawei_zones ALTER COLUMN id SET DEFAULT nextval('public.huawei_zones_id_seq'::regclass);


--
-- Name: interface_history id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.interface_history ALTER COLUMN id SET DEFAULT nextval('public.interface_history_id_seq'::regclass);


--
-- Name: inventory_audit_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_audit_items ALTER COLUMN id SET DEFAULT nextval('public.inventory_audit_items_id_seq'::regclass);


--
-- Name: inventory_audits id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_audits ALTER COLUMN id SET DEFAULT nextval('public.inventory_audits_id_seq'::regclass);


--
-- Name: inventory_locations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_locations ALTER COLUMN id SET DEFAULT nextval('public.inventory_locations_id_seq'::regclass);


--
-- Name: inventory_loss_reports id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_loss_reports ALTER COLUMN id SET DEFAULT nextval('public.inventory_loss_reports_id_seq'::regclass);


--
-- Name: inventory_po_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_po_items ALTER COLUMN id SET DEFAULT nextval('public.inventory_po_items_id_seq'::regclass);


--
-- Name: inventory_purchase_orders id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_purchase_orders ALTER COLUMN id SET DEFAULT nextval('public.inventory_purchase_orders_id_seq'::regclass);


--
-- Name: inventory_receipt_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_receipt_items ALTER COLUMN id SET DEFAULT nextval('public.inventory_receipt_items_id_seq'::regclass);


--
-- Name: inventory_receipts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_receipts ALTER COLUMN id SET DEFAULT nextval('public.inventory_receipts_id_seq'::regclass);


--
-- Name: inventory_return_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_return_items ALTER COLUMN id SET DEFAULT nextval('public.inventory_return_items_id_seq'::regclass);


--
-- Name: inventory_returns id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_returns ALTER COLUMN id SET DEFAULT nextval('public.inventory_returns_id_seq'::regclass);


--
-- Name: inventory_rma id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_rma ALTER COLUMN id SET DEFAULT nextval('public.inventory_rma_id_seq'::regclass);


--
-- Name: inventory_stock_levels id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_stock_levels ALTER COLUMN id SET DEFAULT nextval('public.inventory_stock_levels_id_seq'::regclass);


--
-- Name: inventory_stock_movements id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_stock_movements ALTER COLUMN id SET DEFAULT nextval('public.inventory_stock_movements_id_seq'::regclass);


--
-- Name: inventory_stock_request_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_stock_request_items ALTER COLUMN id SET DEFAULT nextval('public.inventory_stock_request_items_id_seq'::regclass);


--
-- Name: inventory_stock_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_stock_requests ALTER COLUMN id SET DEFAULT nextval('public.inventory_stock_requests_id_seq'::regclass);


--
-- Name: inventory_thresholds id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_thresholds ALTER COLUMN id SET DEFAULT nextval('public.inventory_thresholds_id_seq'::regclass);


--
-- Name: inventory_usage id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_usage ALTER COLUMN id SET DEFAULT nextval('public.inventory_usage_id_seq'::regclass);


--
-- Name: inventory_warehouses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventory_warehouses ALTER COLUMN id SET DEFAULT nextval('public.inventory_warehouses_id_seq'::regclass);


--
-- Name: invoice_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoice_items ALTER COLUMN id SET DEFAULT nextval('public.invoice_items_id_seq'::regclass);


--
-- Name: invoices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoices ALTER COLUMN id SET DEFAULT nextval('public.invoices_id_seq'::regclass);


--
-- Name: isp_locations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.isp_locations ALTER COLUMN id SET DEFAULT nextval('public.isp_locations_id_seq'::regclass);


--
-- Name: isp_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.isp_settings ALTER COLUMN id SET DEFAULT nextval('public.isp_settings_id_seq'::regclass);


--
-- Name: isp_sub_locations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.isp_sub_locations ALTER COLUMN id SET DEFAULT nextval('public.isp_sub_locations_id_seq'::regclass);


--
-- Name: late_rules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.late_rules ALTER COLUMN id SET DEFAULT nextval('public.late_rules_id_seq'::regclass);


--
-- Name: leave_balances id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_balances ALTER COLUMN id SET DEFAULT nextval('public.leave_balances_id_seq'::regclass);


--
-- Name: leave_calendar id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_calendar ALTER COLUMN id SET DEFAULT nextval('public.leave_calendar_id_seq'::regclass);


--
-- Name: leave_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_requests ALTER COLUMN id SET DEFAULT nextval('public.leave_requests_id_seq'::regclass);


--
-- Name: leave_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leave_types ALTER COLUMN id SET DEFAULT nextval('public.leave_types_id_seq'::regclass);


--
-- Name: license_activations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.license_activations ALTER COLUMN id SET DEFAULT nextval('public.license_activations_id_seq'::regclass);


--
-- Name: license_customers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.license_customers ALTER COLUMN id SET DEFAULT nextval('public.license_customers_id_seq'::regclass);


--
-- Name: license_payments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.license_payments ALTER COLUMN id SET DEFAULT nextval('public.license_payments_id_seq'::regclass);


--
-- Name: license_products id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.license_products ALTER COLUMN id SET DEFAULT nextval('public.license_products_id_seq'::regclass);


--
-- Name: license_subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.license_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.license_subscriptions_id_seq'::regclass);


--
-- Name: license_tiers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.license_tiers ALTER COLUMN id SET DEFAULT nextval('public.license_tiers_id_seq'::regclass);


--
-- Name: license_validation_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.license_validation_logs ALTER COLUMN id SET DEFAULT nextval('public.license_validation_logs_id_seq'::regclass);


--
-- Name: licenses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.licenses ALTER COLUMN id SET DEFAULT nextval('public.licenses_id_seq'::regclass);


--
-- Name: mikrotik_provisioned_ips id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mikrotik_provisioned_ips ALTER COLUMN id SET DEFAULT nextval('public.mikrotik_provisioned_ips_id_seq'::regclass);


--
-- Name: mikrotik_vlans id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mikrotik_vlans ALTER COLUMN id SET DEFAULT nextval('public.mikrotik_vlans_id_seq'::regclass);


--
-- Name: mobile_notifications id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mobile_notifications ALTER COLUMN id SET DEFAULT nextval('public.mobile_notifications_id_seq'::regclass);


--
-- Name: mobile_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mobile_tokens ALTER COLUMN id SET DEFAULT nextval('public.mobile_tokens_id_seq'::regclass);


--
-- Name: mpesa_b2b_transactions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mpesa_b2b_transactions ALTER COLUMN id SET DEFAULT nextval('public.mpesa_b2b_transactions_id_seq'::regclass);


--
-- Name: mpesa_b2c_transactions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mpesa_b2c_transactions ALTER COLUMN id SET DEFAULT nextval('public.mpesa_b2c_transactions_id_seq'::regclass);


--
-- Name: mpesa_c2b_transactions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mpesa_c2b_transactions ALTER COLUMN id SET DEFAULT nextval('public.mpesa_c2b_transactions_id_seq'::regclass);


--
-- Name: mpesa_config id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mpesa_config ALTER COLUMN id SET DEFAULT nextval('public.mpesa_config_id_seq'::regclass);


--
-- Name: mpesa_transactions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mpesa_transactions ALTER COLUMN id SET DEFAULT nextval('public.mpesa_transactions_id_seq'::regclass);


--
-- Name: network_devices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.network_devices ALTER COLUMN id SET DEFAULT nextval('public.network_devices_id_seq'::regclass);


--
-- Name: onu_discovery_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onu_discovery_log ALTER COLUMN id SET DEFAULT nextval('public.onu_discovery_log_id_seq'::regclass);


--
-- Name: onu_fault_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onu_fault_log ALTER COLUMN id SET DEFAULT nextval('public.onu_fault_log_id_seq'::regclass);


--
-- Name: onu_signal_history id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onu_signal_history ALTER COLUMN id SET DEFAULT nextval('public.onu_signal_history_id_seq'::regclass);


--
-- Name: onu_uptime_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.onu_uptime_log ALTER COLUMN id SET DEFAULT nextval('public.onu_uptime_log_id_seq'::regclass);


--
-- Name: orders id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orders ALTER COLUMN id SET DEFAULT nextval('public.orders_id_seq'::regclass);


--
-- Name: payroll id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll ALTER COLUMN id SET DEFAULT nextval('public.payroll_id_seq'::regclass);


--
-- Name: payroll_commissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_commissions ALTER COLUMN id SET DEFAULT nextval('public.payroll_commissions_id_seq'::regclass);


--
-- Name: performance_reviews id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.performance_reviews ALTER COLUMN id SET DEFAULT nextval('public.performance_reviews_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: portal_otp id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_otp ALTER COLUMN id SET DEFAULT nextval('public.portal_otp_id_seq'::regclass);


--
-- Name: products_services id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products_services ALTER COLUMN id SET DEFAULT nextval('public.products_services_id_seq'::regclass);


--
-- Name: public_holidays id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.public_holidays ALTER COLUMN id SET DEFAULT nextval('public.public_holidays_id_seq'::regclass);


--
-- Name: purchase_order_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items ALTER COLUMN id SET DEFAULT nextval('public.purchase_order_items_id_seq'::regclass);


--
-- Name: purchase_orders id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders ALTER COLUMN id SET DEFAULT nextval('public.purchase_orders_id_seq'::regclass);


--
-- Name: quote_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quote_items ALTER COLUMN id SET DEFAULT nextval('public.quote_items_id_seq'::regclass);


--
-- Name: quotes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quotes ALTER COLUMN id SET DEFAULT nextval('public.quotes_id_seq'::regclass);


--
-- Name: radius_addon_services id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_addon_services ALTER COLUMN id SET DEFAULT nextval('public.radius_addon_services_id_seq'::regclass);


--
-- Name: radius_alert_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_alert_log ALTER COLUMN id SET DEFAULT nextval('public.radius_alert_log_id_seq'::regclass);


--
-- Name: radius_auth_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_auth_logs ALTER COLUMN id SET DEFAULT nextval('public.radius_auth_logs_id_seq'::regclass);


--
-- Name: radius_billing id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_billing ALTER COLUMN id SET DEFAULT nextval('public.radius_billing_id_seq'::regclass);


--
-- Name: radius_billing_history id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_billing_history ALTER COLUMN id SET DEFAULT nextval('public.radius_billing_history_id_seq'::regclass);


--
-- Name: radius_invoices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_invoices ALTER COLUMN id SET DEFAULT nextval('public.radius_invoices_id_seq'::regclass);


--
-- Name: radius_ip_pools id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_ip_pools ALTER COLUMN id SET DEFAULT nextval('public.radius_ip_pools_id_seq'::regclass);


--
-- Name: radius_nas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_nas ALTER COLUMN id SET DEFAULT nextval('public.radius_nas_id_seq'::regclass);


--
-- Name: radius_nas_packages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_nas_packages ALTER COLUMN id SET DEFAULT nextval('public.radius_nas_packages_id_seq'::regclass);


--
-- Name: radius_package_schedules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_package_schedules ALTER COLUMN id SET DEFAULT nextval('public.radius_package_schedules_id_seq'::regclass);


--
-- Name: radius_packages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_packages ALTER COLUMN id SET DEFAULT nextval('public.radius_packages_id_seq'::regclass);


--
-- Name: radius_promotions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_promotions ALTER COLUMN id SET DEFAULT nextval('public.radius_promotions_id_seq'::regclass);


--
-- Name: radius_redirect_rules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_redirect_rules ALTER COLUMN id SET DEFAULT nextval('public.radius_redirect_rules_id_seq'::regclass);


--
-- Name: radius_referrals id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_referrals ALTER COLUMN id SET DEFAULT nextval('public.radius_referrals_id_seq'::regclass);


--
-- Name: radius_sessions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_sessions ALTER COLUMN id SET DEFAULT nextval('public.radius_sessions_id_seq'::regclass);


--
-- Name: radius_speed_overrides id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_speed_overrides ALTER COLUMN id SET DEFAULT nextval('public.radius_speed_overrides_id_seq'::regclass);


--
-- Name: radius_subscription_addons id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_subscription_addons ALTER COLUMN id SET DEFAULT nextval('public.radius_subscription_addons_id_seq'::regclass);


--
-- Name: radius_subscription_devices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_subscription_devices ALTER COLUMN id SET DEFAULT nextval('public.radius_subscription_devices_id_seq'::regclass);


--
-- Name: radius_subscription_macs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_subscription_macs ALTER COLUMN id SET DEFAULT nextval('public.radius_subscription_macs_id_seq'::regclass);


--
-- Name: radius_subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.radius_subscriptions_id_seq'::regclass);


--
-- Name: radius_usage_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_usage_logs ALTER COLUMN id SET DEFAULT nextval('public.radius_usage_logs_id_seq'::regclass);


--
-- Name: radius_vouchers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radius_vouchers ALTER COLUMN id SET DEFAULT nextval('public.radius_vouchers_id_seq'::regclass);


--
-- Name: radpostauth id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.radpostauth ALTER COLUMN id SET DEFAULT nextval('public.radpostauth_id_seq'::regclass);


--
-- Name: role_permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_permissions ALTER COLUMN id SET DEFAULT nextval('public.role_permissions_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: salary_advance_repayments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_advance_repayments ALTER COLUMN id SET DEFAULT nextval('public.salary_advance_repayments_id_seq'::regclass);


--
-- Name: salary_advances id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salary_advances ALTER COLUMN id SET DEFAULT nextval('public.salary_advances_id_seq'::regclass);


--
-- Name: sales_commissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales_commissions ALTER COLUMN id SET DEFAULT nextval('public.sales_commissions_id_seq'::regclass);


--
-- Name: salespersons id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.salespersons ALTER COLUMN id SET DEFAULT nextval('public.salespersons_id_seq'::regclass);


--
-- Name: schema_migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schema_migrations ALTER COLUMN id SET DEFAULT nextval('public.schema_migrations_id_seq'::regclass);


--
-- Name: service_fee_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.service_fee_types ALTER COLUMN id SET DEFAULT nextval('public.service_fee_types_id_seq'::regclass);


--
-- Name: service_packages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.service_packages ALTER COLUMN id SET DEFAULT nextval('public.service_packages_id_seq'::regclass);


--
-- Name: settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.settings ALTER COLUMN id SET DEFAULT nextval('public.settings_id_seq'::regclass);


--
-- Name: sla_business_hours id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sla_business_hours ALTER COLUMN id SET DEFAULT nextval('public.sla_business_hours_id_seq'::regclass);


--
-- Name: sla_holidays id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sla_holidays ALTER COLUMN id SET DEFAULT nextval('public.sla_holidays_id_seq'::regclass);


--
-- Name: sla_policies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sla_policies ALTER COLUMN id SET DEFAULT nextval('public.sla_policies_id_seq'::regclass);


--
-- Name: sms_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sms_logs ALTER COLUMN id SET DEFAULT nextval('public.sms_logs_id_seq'::regclass);


--
-- Name: tax_rates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tax_rates ALTER COLUMN id SET DEFAULT nextval('public.tax_rates_id_seq'::regclass);


--
-- Name: team_members id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.team_members ALTER COLUMN id SET DEFAULT nextval('public.team_members_id_seq'::regclass);


--
-- Name: teams id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams ALTER COLUMN id SET DEFAULT nextval('public.teams_id_seq'::regclass);


--
-- Name: technician_kit_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.technician_kit_items ALTER COLUMN id SET DEFAULT nextval('public.technician_kit_items_id_seq'::regclass);


--
-- Name: technician_kits id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.technician_kits ALTER COLUMN id SET DEFAULT nextval('public.technician_kits_id_seq'::regclass);


--
-- Name: ticket_categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_categories ALTER COLUMN id SET DEFAULT nextval('public.ticket_categories_id_seq'::regclass);


--
-- Name: ticket_comments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_comments ALTER COLUMN id SET DEFAULT nextval('public.ticket_comments_id_seq'::regclass);


--
-- Name: ticket_commission_rates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_commission_rates ALTER COLUMN id SET DEFAULT nextval('public.ticket_commission_rates_id_seq'::regclass);


--
-- Name: ticket_earnings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_earnings ALTER COLUMN id SET DEFAULT nextval('public.ticket_earnings_id_seq'::regclass);


--
-- Name: ticket_escalations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_escalations ALTER COLUMN id SET DEFAULT nextval('public.ticket_escalations_id_seq'::regclass);


--
-- Name: ticket_resolution_photos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_resolution_photos ALTER COLUMN id SET DEFAULT nextval('public.ticket_resolution_photos_id_seq'::regclass);


--
-- Name: ticket_resolutions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_resolutions ALTER COLUMN id SET DEFAULT nextval('public.ticket_resolutions_id_seq'::regclass);


--
-- Name: ticket_satisfaction_ratings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_satisfaction_ratings ALTER COLUMN id SET DEFAULT nextval('public.ticket_satisfaction_ratings_id_seq'::regclass);


--
-- Name: ticket_service_fees id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_service_fees ALTER COLUMN id SET DEFAULT nextval('public.ticket_service_fees_id_seq'::regclass);


--
-- Name: ticket_sla_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_sla_logs ALTER COLUMN id SET DEFAULT nextval('public.ticket_sla_logs_id_seq'::regclass);


--
-- Name: ticket_status_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_status_tokens ALTER COLUMN id SET DEFAULT nextval('public.ticket_status_tokens_id_seq'::regclass);


--
-- Name: ticket_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_templates ALTER COLUMN id SET DEFAULT nextval('public.ticket_templates_id_seq'::regclass);


--
-- Name: tickets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets ALTER COLUMN id SET DEFAULT nextval('public.tickets_id_seq'::regclass);


--
-- Name: tr069_devices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tr069_devices ALTER COLUMN id SET DEFAULT nextval('public.tr069_devices_id_seq'::regclass);


--
-- Name: user_notifications id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_notifications ALTER COLUMN id SET DEFAULT nextval('public.user_notifications_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: vendor_bill_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_bill_items ALTER COLUMN id SET DEFAULT nextval('public.vendor_bill_items_id_seq'::regclass);


--
-- Name: vendor_bills id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_bills ALTER COLUMN id SET DEFAULT nextval('public.vendor_bills_id_seq'::regclass);


--
-- Name: vendor_payments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_payments ALTER COLUMN id SET DEFAULT nextval('public.vendor_payments_id_seq'::regclass);


--
-- Name: vendors id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendors ALTER COLUMN id SET DEFAULT nextval('public.vendors_id_seq'::regclass);


--
-- Name: vlan_history id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vlan_history ALTER COLUMN id SET DEFAULT nextval('public.vlan_history_id_seq'::regclass);


--
-- Name: vlan_traffic_history id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vlan_traffic_history ALTER COLUMN id SET DEFAULT nextval('public.vlan_traffic_history_id_seq'::regclass);


--
-- Name: whatsapp_conversations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_conversations ALTER COLUMN id SET DEFAULT nextval('public.whatsapp_conversations_id_seq'::regclass);


--
-- Name: whatsapp_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_logs ALTER COLUMN id SET DEFAULT nextval('public.whatsapp_logs_id_seq'::regclass);


--
-- Name: whatsapp_messages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.whatsapp_messages ALTER COLUMN id SET DEFAULT nextval('public.whatsapp_messages_id_seq'::regclass);


--
-- Name: wireguard_peers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wireguard_peers ALTER COLUMN id SET DEFAULT nextval('public.wireguard_peers_id_seq'::regclass);


--
-- Name: wireguard_servers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wireguard_servers ALTER COLUMN id SET DEFAULT nextval('public.wireguard_servers_id_seq'::regclass);


--
-- Name: wireguard_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wireguard_settings ALTER COLUMN id SET DEFAULT nextval('public.wireguard_settings_id_seq'::regclass);


--
-- Name: wireguard_subnets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wireguard_subnets ALTER COLUMN id SET DEFAULT nextval('public.wireguard_subnets_id_seq'::regclass);


--
-- Name: wireguard_sync_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wireguard_sync_logs ALTER COLUMN id SET DEFAULT nextval('public.wireguard_sync_logs_id_seq'::regclass);


--
-- Name: absent_deduction_rules absent_deduction_rules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.absent_deduction_rules ADD CONSTRAINT absent_deduction_rules_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: accounting_settings accounting_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.accounting_settings ADD CONSTRAINT accounting_settings_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: accounting_settings accounting_settings_setting_key_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.accounting_settings ADD CONSTRAINT accounting_settings_setting_key_key UNIQUE (setting_key);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: activity_logs activity_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.activity_logs ADD CONSTRAINT activity_logs_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: announcement_recipients announcement_recipients_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.announcement_recipients ADD CONSTRAINT announcement_recipients_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: announcements announcements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.announcements ADD CONSTRAINT announcements_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: attendance attendance_employee_id_date_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.attendance ADD CONSTRAINT attendance_employee_id_date_key UNIQUE (employee_id, date);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: attendance_notification_logs attendance_notification_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.attendance_notification_logs ADD CONSTRAINT attendance_notification_logs_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: attendance attendance_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.attendance ADD CONSTRAINT attendance_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: bill_reminders bill_reminders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.bill_reminders ADD CONSTRAINT bill_reminders_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: biometric_attendance_logs biometric_attendance_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.biometric_attendance_logs ADD CONSTRAINT biometric_attendance_logs_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: biometric_devices biometric_devices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.biometric_devices ADD CONSTRAINT biometric_devices_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: branch_employees branch_employees_branch_id_employee_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.branch_employees ADD CONSTRAINT branch_employees_branch_id_employee_id_key UNIQUE (branch_id, employee_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: branch_employees branch_employees_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.branch_employees ADD CONSTRAINT branch_employees_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: branches branches_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.branches ADD CONSTRAINT branches_code_key UNIQUE (code);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: branches branches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.branches ADD CONSTRAINT branches_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_agent_status call_center_agent_status_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_agent_status ADD CONSTRAINT call_center_agent_status_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_calls call_center_calls_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_calls ADD CONSTRAINT call_center_calls_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_extensions call_center_extensions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_extensions ADD CONSTRAINT call_center_extensions_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_inbound_routes call_center_inbound_routes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_inbound_routes ADD CONSTRAINT call_center_inbound_routes_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_ivr_options call_center_ivr_options_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_ivr_options ADD CONSTRAINT call_center_ivr_options_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_ivr call_center_ivr_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_ivr ADD CONSTRAINT call_center_ivr_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_outbound_routes call_center_outbound_routes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_outbound_routes ADD CONSTRAINT call_center_outbound_routes_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_queue_members call_center_queue_members_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_queue_members ADD CONSTRAINT call_center_queue_members_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_queues call_center_queues_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_queues ADD CONSTRAINT call_center_queues_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_ring_group_members call_center_ring_group_members_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_ring_group_members ADD CONSTRAINT call_center_ring_group_members_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_ring_group_members call_center_ring_group_members_ring_group_id_extension_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_ring_group_members ADD CONSTRAINT call_center_ring_group_members_ring_group_id_extension_id_key UNIQUE (ring_group_id, extension_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_ring_groups call_center_ring_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_ring_groups ADD CONSTRAINT call_center_ring_groups_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_settings call_center_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_settings ADD CONSTRAINT call_center_settings_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_settings call_center_settings_setting_key_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_settings ADD CONSTRAINT call_center_settings_setting_key_key UNIQUE (setting_key);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_time_conditions call_center_time_conditions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_time_conditions ADD CONSTRAINT call_center_time_conditions_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_trunks call_center_trunks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_trunks ADD CONSTRAINT call_center_trunks_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: chart_of_accounts chart_of_accounts_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.chart_of_accounts ADD CONSTRAINT chart_of_accounts_code_key UNIQUE (code);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: chart_of_accounts chart_of_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.chart_of_accounts ADD CONSTRAINT chart_of_accounts_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: company_settings company_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.company_settings ADD CONSTRAINT company_settings_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: company_settings company_settings_setting_key_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.company_settings ADD CONSTRAINT company_settings_setting_key_key UNIQUE (setting_key);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: complaints complaints_complaint_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.complaints ADD CONSTRAINT complaints_complaint_number_key UNIQUE (complaint_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: complaints complaints_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.complaints ADD CONSTRAINT complaints_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: customer_payments customer_payments_payment_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.customer_payments ADD CONSTRAINT customer_payments_payment_number_key UNIQUE (payment_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: customer_payments customer_payments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.customer_payments ADD CONSTRAINT customer_payments_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: customer_ticket_tokens customer_ticket_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.customer_ticket_tokens ADD CONSTRAINT customer_ticket_tokens_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: customers customers_account_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.customers ADD CONSTRAINT customers_account_number_key UNIQUE (account_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: customers customers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.customers ADD CONSTRAINT customers_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: departments departments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.departments ADD CONSTRAINT departments_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: device_interfaces device_interfaces_device_id_if_index_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.device_interfaces ADD CONSTRAINT device_interfaces_device_id_if_index_key UNIQUE (device_id, if_index);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: device_interfaces device_interfaces_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.device_interfaces ADD CONSTRAINT device_interfaces_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: device_monitoring_log device_monitoring_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.device_monitoring_log ADD CONSTRAINT device_monitoring_log_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: device_onus device_onus_device_id_onu_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.device_onus ADD CONSTRAINT device_onus_device_id_onu_id_key UNIQUE (device_id, onu_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: device_onus device_onus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.device_onus ADD CONSTRAINT device_onus_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: device_user_mapping device_user_mapping_device_id_device_user_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.device_user_mapping ADD CONSTRAINT device_user_mapping_device_id_device_user_id_key UNIQUE (device_id, device_user_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: device_user_mapping device_user_mapping_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.device_user_mapping ADD CONSTRAINT device_user_mapping_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: device_vlans device_vlans_device_id_vlan_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.device_vlans ADD CONSTRAINT device_vlans_device_id_vlan_id_key UNIQUE (device_id, vlan_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: device_vlans device_vlans_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.device_vlans ADD CONSTRAINT device_vlans_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: email_logs email_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.email_logs ADD CONSTRAINT email_logs_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: employee_branches employee_branches_employee_id_branch_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.employee_branches ADD CONSTRAINT employee_branches_employee_id_branch_id_key UNIQUE (employee_id, branch_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: employee_branches employee_branches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.employee_branches ADD CONSTRAINT employee_branches_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: employee_kyc_documents employee_kyc_documents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.employee_kyc_documents ADD CONSTRAINT employee_kyc_documents_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: employees employees_employee_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.employees ADD CONSTRAINT employees_employee_id_key UNIQUE (employee_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: employees employees_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.employees ADD CONSTRAINT employees_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: equipment_assignments equipment_assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment_assignments ADD CONSTRAINT equipment_assignments_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: equipment_categories equipment_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment_categories ADD CONSTRAINT equipment_categories_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: equipment_faults equipment_faults_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment_faults ADD CONSTRAINT equipment_faults_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: equipment_lifecycle_logs equipment_lifecycle_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment_lifecycle_logs ADD CONSTRAINT equipment_lifecycle_logs_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: equipment_loans equipment_loans_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment_loans ADD CONSTRAINT equipment_loans_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: equipment equipment_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment ADD CONSTRAINT equipment_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: equipment equipment_serial_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment ADD CONSTRAINT equipment_serial_number_key UNIQUE (serial_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: expense_categories expense_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.expense_categories ADD CONSTRAINT expense_categories_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: expenses expenses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.expenses ADD CONSTRAINT expenses_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: genieacs_config genieacs_config_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.genieacs_config ADD CONSTRAINT genieacs_config_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: hr_notification_templates hr_notification_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.hr_notification_templates ADD CONSTRAINT hr_notification_templates_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_alerts huawei_alerts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_alerts ADD CONSTRAINT huawei_alerts_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_apartments huawei_apartments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_apartments ADD CONSTRAINT huawei_apartments_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_apartments huawei_apartments_zone_id_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_apartments ADD CONSTRAINT huawei_apartments_zone_id_name_key UNIQUE (zone_id, name);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_boards huawei_boards_olt_id_slot_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_boards ADD CONSTRAINT huawei_boards_olt_id_slot_key UNIQUE (olt_id, slot);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_boards huawei_boards_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_boards ADD CONSTRAINT huawei_boards_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_dba_profiles huawei_dba_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_dba_profiles ADD CONSTRAINT huawei_dba_profiles_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_odb_units huawei_odb_units_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_odb_units ADD CONSTRAINT huawei_odb_units_code_key UNIQUE (code);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_odb_units huawei_odb_units_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_odb_units ADD CONSTRAINT huawei_odb_units_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olt_boards huawei_olt_boards_olt_id_slot_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olt_boards ADD CONSTRAINT huawei_olt_boards_olt_id_slot_key UNIQUE (olt_id, slot);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olt_boards huawei_olt_boards_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olt_boards ADD CONSTRAINT huawei_olt_boards_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olt_line_profiles huawei_olt_line_profiles_olt_id_profile_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olt_line_profiles ADD CONSTRAINT huawei_olt_line_profiles_olt_id_profile_id_key UNIQUE (olt_id, profile_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olt_line_profiles huawei_olt_line_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olt_line_profiles ADD CONSTRAINT huawei_olt_line_profiles_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olt_pon_ports huawei_olt_pon_ports_olt_id_port_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olt_pon_ports ADD CONSTRAINT huawei_olt_pon_ports_olt_id_port_name_key UNIQUE (olt_id, port_name);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olt_pon_ports huawei_olt_pon_ports_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olt_pon_ports ADD CONSTRAINT huawei_olt_pon_ports_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olt_srv_profiles huawei_olt_srv_profiles_olt_id_profile_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olt_srv_profiles ADD CONSTRAINT huawei_olt_srv_profiles_olt_id_profile_id_key UNIQUE (olt_id, profile_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olt_srv_profiles huawei_olt_srv_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olt_srv_profiles ADD CONSTRAINT huawei_olt_srv_profiles_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olt_uplinks huawei_olt_uplinks_olt_id_port_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olt_uplinks ADD CONSTRAINT huawei_olt_uplinks_olt_id_port_name_key UNIQUE (olt_id, port_name);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olt_uplinks huawei_olt_uplinks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olt_uplinks ADD CONSTRAINT huawei_olt_uplinks_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olt_vlans huawei_olt_vlans_olt_id_vlan_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olt_vlans ADD CONSTRAINT huawei_olt_vlans_olt_id_vlan_id_key UNIQUE (olt_id, vlan_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olt_vlans huawei_olt_vlans_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olt_vlans ADD CONSTRAINT huawei_olt_vlans_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olts huawei_olts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olts ADD CONSTRAINT huawei_olts_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onu_mgmt_ips huawei_onu_mgmt_ips_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onu_mgmt_ips ADD CONSTRAINT huawei_onu_mgmt_ips_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onu_service_vlans huawei_onu_service_vlans_onu_id_vlan_id_interface_type_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onu_service_vlans ADD CONSTRAINT huawei_onu_service_vlans_onu_id_vlan_id_interface_type_key UNIQUE (onu_id, vlan_id, interface_type);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onu_service_vlans huawei_onu_service_vlans_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onu_service_vlans ADD CONSTRAINT huawei_onu_service_vlans_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onu_tr069_config huawei_onu_tr069_config_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onu_tr069_config ADD CONSTRAINT huawei_onu_tr069_config_pkey PRIMARY KEY (onu_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onu_tr069_logs huawei_onu_tr069_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onu_tr069_logs ADD CONSTRAINT huawei_onu_tr069_logs_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onu_types huawei_onu_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onu_types ADD CONSTRAINT huawei_onu_types_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onus huawei_onus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onus ADD CONSTRAINT huawei_onus_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_pon_ports huawei_pon_ports_olt_id_frame_slot_port_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_pon_ports ADD CONSTRAINT huawei_pon_ports_olt_id_frame_slot_port_key UNIQUE (olt_id, frame, slot, port);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_pon_ports huawei_pon_ports_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_pon_ports ADD CONSTRAINT huawei_pon_ports_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_port_vlans huawei_port_vlans_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_port_vlans ADD CONSTRAINT huawei_port_vlans_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_provisioning_logs huawei_provisioning_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_provisioning_logs ADD CONSTRAINT huawei_provisioning_logs_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_service_profiles huawei_service_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_service_profiles ADD CONSTRAINT huawei_service_profiles_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_service_templates huawei_service_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_service_templates ADD CONSTRAINT huawei_service_templates_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_subzones huawei_subzones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_subzones ADD CONSTRAINT huawei_subzones_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_subzones huawei_subzones_zone_id_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_subzones ADD CONSTRAINT huawei_subzones_zone_id_name_key UNIQUE (zone_id, name);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_uplinks huawei_uplinks_olt_id_frame_slot_port_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_uplinks ADD CONSTRAINT huawei_uplinks_olt_id_frame_slot_port_key UNIQUE (olt_id, frame, slot, port);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_uplinks huawei_uplinks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_uplinks ADD CONSTRAINT huawei_uplinks_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_vlans huawei_vlans_olt_id_vlan_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_vlans ADD CONSTRAINT huawei_vlans_olt_id_vlan_id_key UNIQUE (olt_id, vlan_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_vlans huawei_vlans_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_vlans ADD CONSTRAINT huawei_vlans_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_zones huawei_zones_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_zones ADD CONSTRAINT huawei_zones_name_key UNIQUE (name);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_zones huawei_zones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_zones ADD CONSTRAINT huawei_zones_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: interface_history interface_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.interface_history ADD CONSTRAINT interface_history_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_audit_items inventory_audit_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_audit_items ADD CONSTRAINT inventory_audit_items_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_audits inventory_audits_audit_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_audits ADD CONSTRAINT inventory_audits_audit_number_key UNIQUE (audit_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_audits inventory_audits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_audits ADD CONSTRAINT inventory_audits_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_locations inventory_locations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_locations ADD CONSTRAINT inventory_locations_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_loss_reports inventory_loss_reports_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_loss_reports ADD CONSTRAINT inventory_loss_reports_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_loss_reports inventory_loss_reports_report_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_loss_reports ADD CONSTRAINT inventory_loss_reports_report_number_key UNIQUE (report_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_po_items inventory_po_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_po_items ADD CONSTRAINT inventory_po_items_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_purchase_orders inventory_purchase_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_purchase_orders ADD CONSTRAINT inventory_purchase_orders_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_purchase_orders inventory_purchase_orders_po_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_purchase_orders ADD CONSTRAINT inventory_purchase_orders_po_number_key UNIQUE (po_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_receipt_items inventory_receipt_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_receipt_items ADD CONSTRAINT inventory_receipt_items_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_receipts inventory_receipts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_receipts ADD CONSTRAINT inventory_receipts_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_receipts inventory_receipts_receipt_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_receipts ADD CONSTRAINT inventory_receipts_receipt_number_key UNIQUE (receipt_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_return_items inventory_return_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_return_items ADD CONSTRAINT inventory_return_items_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_returns inventory_returns_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_returns ADD CONSTRAINT inventory_returns_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_returns inventory_returns_return_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_returns ADD CONSTRAINT inventory_returns_return_number_key UNIQUE (return_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_rma inventory_rma_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_rma ADD CONSTRAINT inventory_rma_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_rma inventory_rma_rma_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_rma ADD CONSTRAINT inventory_rma_rma_number_key UNIQUE (rma_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_levels inventory_stock_levels_category_id_warehouse_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_levels ADD CONSTRAINT inventory_stock_levels_category_id_warehouse_id_key UNIQUE (category_id, warehouse_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_levels inventory_stock_levels_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_levels ADD CONSTRAINT inventory_stock_levels_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_movements inventory_stock_movements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_movements ADD CONSTRAINT inventory_stock_movements_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_request_items inventory_stock_request_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_request_items ADD CONSTRAINT inventory_stock_request_items_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_requests inventory_stock_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_requests ADD CONSTRAINT inventory_stock_requests_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_requests inventory_stock_requests_request_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_requests ADD CONSTRAINT inventory_stock_requests_request_number_key UNIQUE (request_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_thresholds inventory_thresholds_category_id_warehouse_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_thresholds ADD CONSTRAINT inventory_thresholds_category_id_warehouse_id_key UNIQUE (category_id, warehouse_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_thresholds inventory_thresholds_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_thresholds ADD CONSTRAINT inventory_thresholds_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_usage inventory_usage_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_usage ADD CONSTRAINT inventory_usage_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_warehouses inventory_warehouses_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_warehouses ADD CONSTRAINT inventory_warehouses_code_key UNIQUE (code);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_warehouses inventory_warehouses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_warehouses ADD CONSTRAINT inventory_warehouses_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: invoice_items invoice_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.invoice_items ADD CONSTRAINT invoice_items_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: invoices invoices_invoice_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.invoices ADD CONSTRAINT invoices_invoice_number_key UNIQUE (invoice_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: invoices invoices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.invoices ADD CONSTRAINT invoices_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: isp_locations isp_locations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.isp_locations ADD CONSTRAINT isp_locations_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: isp_settings isp_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.isp_settings ADD CONSTRAINT isp_settings_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: isp_settings isp_settings_setting_key_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.isp_settings ADD CONSTRAINT isp_settings_setting_key_key UNIQUE (setting_key);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: isp_sub_locations isp_sub_locations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.isp_sub_locations ADD CONSTRAINT isp_sub_locations_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: late_rules late_rules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.late_rules ADD CONSTRAINT late_rules_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: leave_balances leave_balances_employee_id_leave_type_id_year_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.leave_balances ADD CONSTRAINT leave_balances_employee_id_leave_type_id_year_key UNIQUE (employee_id, leave_type_id, year);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: leave_balances leave_balances_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.leave_balances ADD CONSTRAINT leave_balances_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: leave_calendar leave_calendar_date_branch_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.leave_calendar ADD CONSTRAINT leave_calendar_date_branch_id_key UNIQUE (date, branch_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: leave_calendar leave_calendar_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.leave_calendar ADD CONSTRAINT leave_calendar_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: leave_requests leave_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.leave_requests ADD CONSTRAINT leave_requests_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: leave_types leave_types_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.leave_types ADD CONSTRAINT leave_types_code_key UNIQUE (code);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: leave_types leave_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.leave_types ADD CONSTRAINT leave_types_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: license_activations license_activations_activation_token_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_activations ADD CONSTRAINT license_activations_activation_token_key UNIQUE (activation_token);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: license_activations license_activations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_activations ADD CONSTRAINT license_activations_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: license_customers license_customers_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_customers ADD CONSTRAINT license_customers_email_key UNIQUE (email);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: license_customers license_customers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_customers ADD CONSTRAINT license_customers_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: license_payments license_payments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_payments ADD CONSTRAINT license_payments_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: license_products license_products_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_products ADD CONSTRAINT license_products_code_key UNIQUE (code);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: license_products license_products_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_products ADD CONSTRAINT license_products_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: license_subscriptions license_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_subscriptions ADD CONSTRAINT license_subscriptions_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: license_tiers license_tiers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_tiers ADD CONSTRAINT license_tiers_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: license_tiers license_tiers_product_id_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_tiers ADD CONSTRAINT license_tiers_product_id_code_key UNIQUE (product_id, code);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: license_validation_logs license_validation_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_validation_logs ADD CONSTRAINT license_validation_logs_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: licenses licenses_license_key_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.licenses ADD CONSTRAINT licenses_license_key_key UNIQUE (license_key);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: licenses licenses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.licenses ADD CONSTRAINT licenses_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: mikrotik_provisioned_ips mikrotik_provisioned_ips_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mikrotik_provisioned_ips ADD CONSTRAINT mikrotik_provisioned_ips_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: mikrotik_vlans mikrotik_vlans_nas_id_vlan_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mikrotik_vlans ADD CONSTRAINT mikrotik_vlans_nas_id_vlan_id_key UNIQUE (nas_id, vlan_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: mikrotik_vlans mikrotik_vlans_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mikrotik_vlans ADD CONSTRAINT mikrotik_vlans_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: mobile_notifications mobile_notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mobile_notifications ADD CONSTRAINT mobile_notifications_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: mobile_tokens mobile_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mobile_tokens ADD CONSTRAINT mobile_tokens_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: mobile_tokens mobile_tokens_user_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mobile_tokens ADD CONSTRAINT mobile_tokens_user_id_key UNIQUE (user_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: mpesa_b2b_transactions mpesa_b2b_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mpesa_b2b_transactions ADD CONSTRAINT mpesa_b2b_transactions_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: mpesa_b2b_transactions mpesa_b2b_transactions_request_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mpesa_b2b_transactions ADD CONSTRAINT mpesa_b2b_transactions_request_id_key UNIQUE (request_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: mpesa_b2c_transactions mpesa_b2c_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mpesa_b2c_transactions ADD CONSTRAINT mpesa_b2c_transactions_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: mpesa_b2c_transactions mpesa_b2c_transactions_request_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mpesa_b2c_transactions ADD CONSTRAINT mpesa_b2c_transactions_request_id_key UNIQUE (request_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: mpesa_c2b_transactions mpesa_c2b_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mpesa_c2b_transactions ADD CONSTRAINT mpesa_c2b_transactions_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: mpesa_c2b_transactions mpesa_c2b_transactions_trans_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mpesa_c2b_transactions ADD CONSTRAINT mpesa_c2b_transactions_trans_id_key UNIQUE (trans_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: mpesa_config mpesa_config_config_key_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mpesa_config ADD CONSTRAINT mpesa_config_config_key_key UNIQUE (config_key);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: mpesa_config mpesa_config_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mpesa_config ADD CONSTRAINT mpesa_config_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: mpesa_transactions mpesa_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mpesa_transactions ADD CONSTRAINT mpesa_transactions_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: network_devices network_devices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.network_devices ADD CONSTRAINT network_devices_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: onu_discovery_log onu_discovery_log_olt_id_serial_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.onu_discovery_log ADD CONSTRAINT onu_discovery_log_olt_id_serial_number_key UNIQUE (olt_id, serial_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: onu_discovery_log onu_discovery_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.onu_discovery_log ADD CONSTRAINT onu_discovery_log_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: onu_fault_log onu_fault_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.onu_fault_log ADD CONSTRAINT onu_fault_log_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: onu_signal_history onu_signal_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.onu_signal_history ADD CONSTRAINT onu_signal_history_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: onu_uptime_log onu_uptime_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.onu_uptime_log ADD CONSTRAINT onu_uptime_log_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: orders orders_order_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.orders ADD CONSTRAINT orders_order_number_key UNIQUE (order_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: orders orders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.orders ADD CONSTRAINT orders_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: payroll_commissions payroll_commissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.payroll_commissions ADD CONSTRAINT payroll_commissions_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: payroll payroll_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.payroll ADD CONSTRAINT payroll_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: performance_reviews performance_reviews_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.performance_reviews ADD CONSTRAINT performance_reviews_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: permissions permissions_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.permissions ADD CONSTRAINT permissions_name_key UNIQUE (name);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.permissions ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: portal_otp portal_otp_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.portal_otp ADD CONSTRAINT portal_otp_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: products_services products_services_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.products_services ADD CONSTRAINT products_services_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: public_holidays public_holidays_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.public_holidays ADD CONSTRAINT public_holidays_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: purchase_order_items purchase_order_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.purchase_order_items ADD CONSTRAINT purchase_order_items_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: purchase_orders purchase_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.purchase_orders ADD CONSTRAINT purchase_orders_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: purchase_orders purchase_orders_po_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.purchase_orders ADD CONSTRAINT purchase_orders_po_number_key UNIQUE (po_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: quote_items quote_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.quote_items ADD CONSTRAINT quote_items_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: quotes quotes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.quotes ADD CONSTRAINT quotes_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: quotes quotes_quote_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.quotes ADD CONSTRAINT quotes_quote_number_key UNIQUE (quote_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_addon_services radius_addon_services_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_addon_services ADD CONSTRAINT radius_addon_services_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_alert_log radius_alert_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_alert_log ADD CONSTRAINT radius_alert_log_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_auth_logs radius_auth_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_auth_logs ADD CONSTRAINT radius_auth_logs_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_billing_history radius_billing_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_billing_history ADD CONSTRAINT radius_billing_history_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_billing radius_billing_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_billing ADD CONSTRAINT radius_billing_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_invoices radius_invoices_invoice_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_invoices ADD CONSTRAINT radius_invoices_invoice_number_key UNIQUE (invoice_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_invoices radius_invoices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_invoices ADD CONSTRAINT radius_invoices_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_ip_pools radius_ip_pools_nas_id_ip_address_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_ip_pools ADD CONSTRAINT radius_ip_pools_nas_id_ip_address_key UNIQUE (nas_id, ip_address);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_ip_pools radius_ip_pools_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_ip_pools ADD CONSTRAINT radius_ip_pools_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_nas radius_nas_ip_address_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_nas ADD CONSTRAINT radius_nas_ip_address_key UNIQUE (ip_address);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_nas_packages radius_nas_packages_nas_id_package_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_nas_packages ADD CONSTRAINT radius_nas_packages_nas_id_package_id_key UNIQUE (nas_id, package_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_nas_packages radius_nas_packages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_nas_packages ADD CONSTRAINT radius_nas_packages_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_nas radius_nas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_nas ADD CONSTRAINT radius_nas_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_package_schedules radius_package_schedules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_package_schedules ADD CONSTRAINT radius_package_schedules_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_packages radius_packages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_packages ADD CONSTRAINT radius_packages_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_promotions radius_promotions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_promotions ADD CONSTRAINT radius_promotions_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_redirect_rules radius_redirect_rules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_redirect_rules ADD CONSTRAINT radius_redirect_rules_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_referrals radius_referrals_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_referrals ADD CONSTRAINT radius_referrals_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_sessions radius_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_sessions ADD CONSTRAINT radius_sessions_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_speed_overrides radius_speed_overrides_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_speed_overrides ADD CONSTRAINT radius_speed_overrides_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscription_addons radius_subscription_addons_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscription_addons ADD CONSTRAINT radius_subscription_addons_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscription_addons radius_subscription_addons_subscription_id_addon_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscription_addons ADD CONSTRAINT radius_subscription_addons_subscription_id_addon_id_key UNIQUE (subscription_id, addon_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscription_devices radius_subscription_devices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscription_devices ADD CONSTRAINT radius_subscription_devices_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscription_devices radius_subscription_devices_subscription_id_mac_address_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscription_devices ADD CONSTRAINT radius_subscription_devices_subscription_id_mac_address_key UNIQUE (subscription_id, mac_address);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscription_macs radius_subscription_macs_mac_address_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscription_macs ADD CONSTRAINT radius_subscription_macs_mac_address_unique UNIQUE (mac_address);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscription_macs radius_subscription_macs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscription_macs ADD CONSTRAINT radius_subscription_macs_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscription_macs radius_subscription_macs_subscription_id_mac_address_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscription_macs ADD CONSTRAINT radius_subscription_macs_subscription_id_mac_address_key UNIQUE (subscription_id, mac_address);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscriptions radius_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscriptions ADD CONSTRAINT radius_subscriptions_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscriptions radius_subscriptions_username_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscriptions ADD CONSTRAINT radius_subscriptions_username_key UNIQUE (username);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_usage_logs radius_usage_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_usage_logs ADD CONSTRAINT radius_usage_logs_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_usage_logs radius_usage_logs_subscription_id_log_date_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_usage_logs ADD CONSTRAINT radius_usage_logs_subscription_id_log_date_key UNIQUE (subscription_id, log_date);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_vouchers radius_vouchers_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_vouchers ADD CONSTRAINT radius_vouchers_code_key UNIQUE (code);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radius_vouchers radius_vouchers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_vouchers ADD CONSTRAINT radius_vouchers_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: radpostauth radpostauth_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radpostauth ADD CONSTRAINT radpostauth_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: role_permissions role_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.role_permissions ADD CONSTRAINT role_permissions_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: role_permissions role_permissions_role_id_permission_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.role_permissions ADD CONSTRAINT role_permissions_role_id_permission_id_key UNIQUE (role_id, permission_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: roles roles_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.roles ADD CONSTRAINT roles_name_key UNIQUE (name);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.roles ADD CONSTRAINT roles_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: salary_advance_repayments salary_advance_repayments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.salary_advance_repayments ADD CONSTRAINT salary_advance_repayments_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: salary_advances salary_advances_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.salary_advances ADD CONSTRAINT salary_advances_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: sales_commissions sales_commissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.sales_commissions ADD CONSTRAINT sales_commissions_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: salespersons salespersons_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.salespersons ADD CONSTRAINT salespersons_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: schema_migrations schema_migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.schema_migrations ADD CONSTRAINT schema_migrations_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: service_fee_types service_fee_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.service_fee_types ADD CONSTRAINT service_fee_types_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: service_packages service_packages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.service_packages ADD CONSTRAINT service_packages_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: service_packages service_packages_slug_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.service_packages ADD CONSTRAINT service_packages_slug_key UNIQUE (slug);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: settings settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.settings ADD CONSTRAINT settings_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: settings settings_setting_key_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.settings ADD CONSTRAINT settings_setting_key_key UNIQUE (setting_key);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: sla_business_hours sla_business_hours_day_of_week_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.sla_business_hours ADD CONSTRAINT sla_business_hours_day_of_week_key UNIQUE (day_of_week);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: sla_business_hours sla_business_hours_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.sla_business_hours ADD CONSTRAINT sla_business_hours_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: sla_holidays sla_holidays_holiday_date_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.sla_holidays ADD CONSTRAINT sla_holidays_holiday_date_key UNIQUE (holiday_date);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: sla_holidays sla_holidays_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.sla_holidays ADD CONSTRAINT sla_holidays_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: sla_policies sla_policies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.sla_policies ADD CONSTRAINT sla_policies_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: sms_logs sms_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.sms_logs ADD CONSTRAINT sms_logs_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: tax_rates tax_rates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.tax_rates ADD CONSTRAINT tax_rates_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: team_members team_members_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.team_members ADD CONSTRAINT team_members_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: team_members team_members_team_id_employee_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.team_members ADD CONSTRAINT team_members_team_id_employee_id_key UNIQUE (team_id, employee_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: teams teams_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.teams ADD CONSTRAINT teams_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: technician_kit_items technician_kit_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.technician_kit_items ADD CONSTRAINT technician_kit_items_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: technician_kits technician_kits_kit_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.technician_kits ADD CONSTRAINT technician_kits_kit_number_key UNIQUE (kit_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: technician_kits technician_kits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.technician_kits ADD CONSTRAINT technician_kits_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_categories ticket_categories_key_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_categories ADD CONSTRAINT ticket_categories_key_key UNIQUE (key);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_categories ticket_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_categories ADD CONSTRAINT ticket_categories_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_comments ticket_comments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_comments ADD CONSTRAINT ticket_comments_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_commission_rates ticket_commission_rates_category_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_commission_rates ADD CONSTRAINT ticket_commission_rates_category_key UNIQUE (category);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_commission_rates ticket_commission_rates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_commission_rates ADD CONSTRAINT ticket_commission_rates_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_earnings ticket_earnings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_earnings ADD CONSTRAINT ticket_earnings_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_escalations ticket_escalations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_escalations ADD CONSTRAINT ticket_escalations_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_resolution_photos ticket_resolution_photos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_resolution_photos ADD CONSTRAINT ticket_resolution_photos_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_resolutions ticket_resolutions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_resolutions ADD CONSTRAINT ticket_resolutions_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_resolutions ticket_resolutions_ticket_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_resolutions ADD CONSTRAINT ticket_resolutions_ticket_id_key UNIQUE (ticket_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_satisfaction_ratings ticket_satisfaction_ratings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_satisfaction_ratings ADD CONSTRAINT ticket_satisfaction_ratings_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_satisfaction_ratings ticket_satisfaction_ratings_ticket_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_satisfaction_ratings ADD CONSTRAINT ticket_satisfaction_ratings_ticket_id_key UNIQUE (ticket_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_service_fees ticket_service_fees_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_service_fees ADD CONSTRAINT ticket_service_fees_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_sla_logs ticket_sla_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_sla_logs ADD CONSTRAINT ticket_sla_logs_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_status_tokens ticket_status_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_status_tokens ADD CONSTRAINT ticket_status_tokens_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_templates ticket_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_templates ADD CONSTRAINT ticket_templates_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: tickets tickets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.tickets ADD CONSTRAINT tickets_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: tickets tickets_ticket_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.tickets ADD CONSTRAINT tickets_ticket_number_key UNIQUE (ticket_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: tr069_devices tr069_devices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.tr069_devices ADD CONSTRAINT tr069_devices_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: tr069_devices tr069_devices_serial_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.tr069_devices ADD CONSTRAINT tr069_devices_serial_number_key UNIQUE (serial_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: user_notifications user_notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.user_notifications ADD CONSTRAINT user_notifications_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.users ADD CONSTRAINT users_email_key UNIQUE (email);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.users ADD CONSTRAINT users_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: vendor_bill_items vendor_bill_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vendor_bill_items ADD CONSTRAINT vendor_bill_items_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: vendor_bills vendor_bills_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vendor_bills ADD CONSTRAINT vendor_bills_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: vendor_payments vendor_payments_payment_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vendor_payments ADD CONSTRAINT vendor_payments_payment_number_key UNIQUE (payment_number);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: vendor_payments vendor_payments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vendor_payments ADD CONSTRAINT vendor_payments_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: vendors vendors_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vendors ADD CONSTRAINT vendors_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: vlan_history vlan_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vlan_history ADD CONSTRAINT vlan_history_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: vlan_traffic_history vlan_traffic_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vlan_traffic_history ADD CONSTRAINT vlan_traffic_history_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: whatsapp_conversations whatsapp_conversations_chat_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.whatsapp_conversations ADD CONSTRAINT whatsapp_conversations_chat_id_key UNIQUE (chat_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: whatsapp_conversations whatsapp_conversations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.whatsapp_conversations ADD CONSTRAINT whatsapp_conversations_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: whatsapp_logs whatsapp_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.whatsapp_logs ADD CONSTRAINT whatsapp_logs_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: whatsapp_messages whatsapp_messages_message_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.whatsapp_messages ADD CONSTRAINT whatsapp_messages_message_id_key UNIQUE (message_id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: whatsapp_messages whatsapp_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.whatsapp_messages ADD CONSTRAINT whatsapp_messages_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: wireguard_peers wireguard_peers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.wireguard_peers ADD CONSTRAINT wireguard_peers_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: wireguard_servers wireguard_servers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.wireguard_servers ADD CONSTRAINT wireguard_servers_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: wireguard_settings wireguard_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.wireguard_settings ADD CONSTRAINT wireguard_settings_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: wireguard_settings wireguard_settings_setting_key_key; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.wireguard_settings ADD CONSTRAINT wireguard_settings_setting_key_key UNIQUE (setting_key);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: wireguard_subnets wireguard_subnets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.wireguard_subnets ADD CONSTRAINT wireguard_subnets_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: wireguard_sync_logs wireguard_sync_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.wireguard_sync_logs ADD CONSTRAINT wireguard_sync_logs_pkey PRIMARY KEY (id);
EXCEPTION WHEN duplicate_table OR duplicate_object THEN NULL;
END $$;


--
-- Name: idx_activations_domain; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_activations_domain ON public.license_activations USING btree (domain);


--
-- Name: idx_activations_license; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_activations_license ON public.license_activations USING btree (license_id);


--
-- Name: idx_activations_token; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_activations_token ON public.license_activations USING btree (activation_token);


--
-- Name: idx_activity_logs_action; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_activity_logs_action ON public.activity_logs USING btree (action_type);


--
-- Name: idx_activity_logs_created; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_activity_logs_created ON public.activity_logs USING btree (created_at);


--
-- Name: idx_activity_logs_entity; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_activity_logs_entity ON public.activity_logs USING btree (entity_type);


--
-- Name: idx_activity_logs_user; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_activity_logs_user ON public.activity_logs USING btree (user_id);


--
-- Name: idx_addon_services_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_addon_services_active ON public.radius_addon_services USING btree (is_active);


--
-- Name: idx_announcement_recipients_announcement; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_announcement_recipients_announcement ON public.announcement_recipients USING btree (announcement_id);


--
-- Name: idx_announcement_recipients_employee; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_announcement_recipients_employee ON public.announcement_recipients USING btree (employee_id);


--
-- Name: idx_announcements_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_announcements_status ON public.announcements USING btree (status);


--
-- Name: idx_attendance_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_attendance_date ON public.attendance USING btree (date);


--
-- Name: idx_attendance_employee; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_attendance_employee ON public.attendance USING btree (employee_id);


--
-- Name: idx_attendance_employee_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_attendance_employee_date ON public.attendance USING btree (employee_id, date);


--
-- Name: idx_attendance_notification_logs_employee_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_attendance_notification_logs_employee_date ON public.attendance_notification_logs USING btree (employee_id, attendance_date);


--
-- Name: idx_b2b_created; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_b2b_created ON public.mpesa_b2b_transactions USING btree (created_at DESC);


--
-- Name: idx_b2b_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_b2b_status ON public.mpesa_b2b_transactions USING btree (status);


--
-- Name: idx_b2c_created; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_b2c_created ON public.mpesa_b2c_transactions USING btree (created_at DESC);


--
-- Name: idx_b2c_linked; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_b2c_linked ON public.mpesa_b2c_transactions USING btree (linked_type, linked_id);


--
-- Name: idx_b2c_phone; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_b2c_phone ON public.mpesa_b2c_transactions USING btree (phone);


--
-- Name: idx_b2c_purpose; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_b2c_purpose ON public.mpesa_b2c_transactions USING btree (purpose);


--
-- Name: idx_b2c_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_b2c_status ON public.mpesa_b2c_transactions USING btree (status);


--
-- Name: idx_bill_reminders_bill; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_bill_reminders_bill ON public.bill_reminders USING btree (bill_id);


--
-- Name: idx_bill_reminders_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_bill_reminders_date ON public.bill_reminders USING btree (reminder_date);


--
-- Name: idx_billing_history_ref; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_billing_history_ref ON public.radius_billing_history USING btree (transaction_ref);


--
-- Name: idx_billing_history_subscription; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_billing_history_subscription ON public.radius_billing_history USING btree (subscription_id);


--
-- Name: idx_branches_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_branches_active ON public.branches USING btree (is_active);


--
-- Name: idx_customer_payments_invoice; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_customer_payments_invoice ON public.customer_payments USING btree (invoice_id);


--
-- Name: idx_customer_ticket_tokens_lookup; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_customer_ticket_tokens_lookup ON public.customer_ticket_tokens USING btree (token_lookup);


--
-- Name: idx_customer_ticket_tokens_ticket; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_customer_ticket_tokens_ticket ON public.customer_ticket_tokens USING btree (ticket_id);


--
-- Name: idx_customers_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_customers_account ON public.customers USING btree (account_number);


--
-- Name: idx_device_onus_customer; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_device_onus_customer ON public.device_onus USING btree (customer_id);


--
-- Name: idx_device_onus_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_device_onus_status ON public.device_onus USING btree (status);


--
-- Name: idx_device_vlans_device; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_device_vlans_device ON public.device_vlans USING btree (device_id);


--
-- Name: idx_employee_branches_branch; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_employee_branches_branch ON public.employee_branches USING btree (branch_id);


--
-- Name: idx_employee_branches_employee; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_employee_branches_employee ON public.employee_branches USING btree (employee_id);


--
-- Name: idx_employees_department; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_employees_department ON public.employees USING btree (department_id);


--
-- Name: idx_employees_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_employees_status ON public.employees USING btree (employment_status);


--
-- Name: idx_expenses_category; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_expenses_category ON public.expenses USING btree (category_id);


--
-- Name: idx_huawei_apartments_subzone; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_apartments_subzone ON public.huawei_apartments USING btree (subzone_id);


--
-- Name: idx_huawei_apartments_zone; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_apartments_zone ON public.huawei_apartments USING btree (zone_id);


--
-- Name: idx_huawei_odb_units_apartment; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_odb_units_apartment ON public.huawei_odb_units USING btree (apartment_id);


--
-- Name: idx_huawei_odb_units_zone; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_odb_units_zone ON public.huawei_odb_units USING btree (zone_id);


--
-- Name: idx_huawei_odb_zone; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_odb_zone ON public.huawei_odb_units USING btree (zone_id);


--
-- Name: idx_huawei_olts_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_olts_active ON public.huawei_olts USING btree (is_active);


--
-- Name: idx_huawei_onus_apartment; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_apartment ON public.huawei_onus USING btree (apartment_id);


--
-- Name: idx_huawei_onus_area; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_area ON public.huawei_onus USING btree (area);


--
-- Name: idx_huawei_onus_customer; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_customer ON public.huawei_onus USING btree (customer_id);


--
-- Name: idx_huawei_onus_is_authorized; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_is_authorized ON public.huawei_onus USING btree (is_authorized);


--
-- Name: idx_huawei_onus_line_profile; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_line_profile ON public.huawei_onus USING btree (line_profile_id);


--
-- Name: idx_huawei_onus_odb; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_odb ON public.huawei_onus USING btree (odb_id);


--
-- Name: idx_huawei_onus_olt_authorized; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_olt_authorized ON public.huawei_onus USING btree (olt_id, is_authorized);


--
-- Name: idx_huawei_onus_olt_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_olt_id ON public.huawei_onus USING btree (olt_id);


--
-- Name: idx_huawei_onus_olt_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_olt_status ON public.huawei_onus USING btree (olt_id, status);


--
-- Name: idx_huawei_onus_onu_type; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_onu_type ON public.huawei_onus USING btree (onu_type_id);


--
-- Name: idx_huawei_onus_phone; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_phone ON public.huawei_onus USING btree (phone);


--
-- Name: idx_huawei_onus_service_profile; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_service_profile ON public.huawei_onus USING btree (service_profile_id);


--
-- Name: idx_huawei_onus_sn; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_sn ON public.huawei_onus USING btree (sn);


--
-- Name: idx_huawei_onus_srv_profile; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_srv_profile ON public.huawei_onus USING btree (srv_profile_id);


--
-- Name: idx_huawei_onus_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_status ON public.huawei_onus USING btree (status);


--
-- Name: idx_huawei_onus_updated; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_updated ON public.huawei_onus USING btree (updated_at);


--
-- Name: idx_huawei_onus_vlan; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_vlan ON public.huawei_onus USING btree (vlan_id);


--
-- Name: idx_huawei_onus_zone; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_zone ON public.huawei_onus USING btree (zone);


--
-- Name: idx_huawei_onus_zone_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_onus_zone_id ON public.huawei_onus USING btree (zone_id);


--
-- Name: idx_huawei_subzones_zone; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_subzones_zone ON public.huawei_subzones USING btree (zone_id);


--
-- Name: idx_huawei_vlans_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_vlans_active ON public.huawei_vlans USING btree (is_active);


--
-- Name: idx_huawei_vlans_olt; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_vlans_olt ON public.huawei_vlans USING btree (olt_id);


--
-- Name: idx_huawei_vlans_vlan_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_huawei_vlans_vlan_id ON public.huawei_vlans USING btree (vlan_id);


--
-- Name: idx_interface_history_time; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_interface_history_time ON public.interface_history USING btree (interface_id, recorded_at);


--
-- Name: idx_invoices_customer; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_invoices_customer ON public.invoices USING btree (customer_id);


--
-- Name: idx_invoices_due_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_invoices_due_date ON public.invoices USING btree (due_date);


--
-- Name: idx_invoices_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_invoices_status ON public.invoices USING btree (status);


--
-- Name: idx_isp_sub_locations_location_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_isp_sub_locations_location_id ON public.isp_sub_locations USING btree (location_id);


--
-- Name: idx_licenses_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_licenses_active ON public.licenses USING btree (is_active, is_suspended);


--
-- Name: idx_licenses_customer; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_licenses_customer ON public.licenses USING btree (customer_id);


--
-- Name: idx_licenses_key; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_licenses_key ON public.licenses USING btree (license_key);


--
-- Name: idx_mobile_notifications_user; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_mobile_notifications_user ON public.mobile_notifications USING btree (user_id, is_read);


--
-- Name: idx_mobile_tokens_expires; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_mobile_tokens_expires ON public.mobile_tokens USING btree (expires_at);


--
-- Name: idx_mobile_tokens_token; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_mobile_tokens_token ON public.mobile_tokens USING btree (token);


--
-- Name: idx_monitoring_log_device; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_monitoring_log_device ON public.device_monitoring_log USING btree (device_id, recorded_at);


--
-- Name: idx_olt_boards_olt; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_olt_boards_olt ON public.huawei_olt_boards USING btree (olt_id);


--
-- Name: idx_olt_pon_ports_olt; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_olt_pon_ports_olt ON public.huawei_olt_pon_ports USING btree (olt_id);


--
-- Name: idx_olt_uplinks_olt; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_olt_uplinks_olt ON public.huawei_olt_uplinks USING btree (olt_id);


--
-- Name: idx_olt_vlans_olt; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_olt_vlans_olt ON public.huawei_olt_vlans USING btree (olt_id);


--
-- Name: idx_onu_discovery_log_sn_olt; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_onu_discovery_log_sn_olt ON public.onu_discovery_log USING btree (serial_number, olt_id);


--
-- Name: idx_onu_service_vlans_onu; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_onu_service_vlans_onu ON public.huawei_onu_service_vlans USING btree (onu_id);


--
-- Name: idx_onu_service_vlans_vlan; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_onu_service_vlans_vlan ON public.huawei_onu_service_vlans USING btree (vlan_id);


--
-- Name: idx_package_schedules_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_package_schedules_active ON public.radius_package_schedules USING btree (is_active) WHERE (is_active = true);


--
-- Name: idx_package_schedules_package; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_package_schedules_package ON public.radius_package_schedules USING btree (package_id);


--
-- Name: idx_payments_license; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_payments_license ON public.license_payments USING btree (license_id);


--
-- Name: idx_payments_mpesa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_payments_mpesa ON public.license_payments USING btree (mpesa_receipt);


--
-- Name: idx_payroll_employee; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_payroll_employee ON public.payroll USING btree (employee_id);


--
-- Name: idx_payroll_period; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_payroll_period ON public.payroll USING btree (pay_period_start, pay_period_end);


--
-- Name: idx_performance_employee; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_performance_employee ON public.performance_reviews USING btree (employee_id);


--
-- Name: idx_port_vlans_olt; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_port_vlans_olt ON public.huawei_port_vlans USING btree (olt_id);


--
-- Name: idx_port_vlans_port; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_port_vlans_port ON public.huawei_port_vlans USING btree (port_name);


--
-- Name: idx_portal_otp_phone; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_portal_otp_phone ON public.portal_otp USING btree (phone, used, expires_at);


--
-- Name: idx_quotes_customer; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_quotes_customer ON public.quotes USING btree (customer_id);


--
-- Name: idx_radius_alert_log_sub; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_alert_log_sub ON public.radius_alert_log USING btree (subscription_id);


--
-- Name: idx_radius_auth_logs_created; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_auth_logs_created ON public.radius_auth_logs USING btree (created_at DESC);


--
-- Name: idx_radius_auth_logs_subscription; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_auth_logs_subscription ON public.radius_auth_logs USING btree (subscription_id);


--
-- Name: idx_radius_auth_logs_username; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_auth_logs_username ON public.radius_auth_logs USING btree (username);


--
-- Name: idx_radius_billing_created; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_billing_created ON public.radius_billing USING btree (created_at);


--
-- Name: idx_radius_billing_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_billing_status ON public.radius_billing USING btree (status);


--
-- Name: idx_radius_invoices_sub; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_invoices_sub ON public.radius_invoices USING btree (subscription_id);


--
-- Name: idx_radius_nas_location; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_nas_location ON public.radius_nas USING btree (location_id);


--
-- Name: idx_radius_promotions_code; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_promotions_code ON public.radius_promotions USING btree (promo_code);


--
-- Name: idx_radius_referrals_code; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_referrals_code ON public.radius_referrals USING btree (referral_code);


--
-- Name: idx_radius_sessions_acct_session_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX IF NOT EXISTS idx_radius_sessions_acct_session_id ON public.radius_sessions USING btree (acct_session_id);


--
-- Name: idx_radius_sessions_start; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_sessions_start ON public.radius_sessions USING btree (session_start);


--
-- Name: idx_radius_sessions_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_sessions_status ON public.radius_sessions USING btree (status);


--
-- Name: idx_radius_sessions_subscription; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_sessions_subscription ON public.radius_sessions USING btree (subscription_id);


--
-- Name: idx_radius_subscriptions_expiry; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_subscriptions_expiry ON public.radius_subscriptions USING btree (expiry_date);


--
-- Name: idx_radius_subscriptions_location; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_subscriptions_location ON public.radius_subscriptions USING btree (location_id);


--
-- Name: idx_radius_subscriptions_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_subscriptions_status ON public.radius_subscriptions USING btree (status);


--
-- Name: idx_radius_subscriptions_username; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_subscriptions_username ON public.radius_subscriptions USING btree (username);


--
-- Name: idx_radius_usage_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_usage_date ON public.radius_usage_logs USING btree (log_date);


--
-- Name: idx_radius_vouchers_code; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_vouchers_code ON public.radius_vouchers USING btree (code);


--
-- Name: idx_radius_vouchers_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radius_vouchers_status ON public.radius_vouchers USING btree (status);


--
-- Name: idx_radpostauth_authdate; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radpostauth_authdate ON public.radpostauth USING btree (authdate);


--
-- Name: idx_radpostauth_username; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_radpostauth_username ON public.radpostauth USING btree (username);


--
-- Name: idx_resolution_photos_ticket; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_resolution_photos_ticket ON public.ticket_resolution_photos USING btree (ticket_id);


--
-- Name: idx_salespersons_employee_id_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX IF NOT EXISTS idx_salespersons_employee_id_unique ON public.salespersons USING btree (employee_id) WHERE (employee_id IS NOT NULL);


--
-- Name: idx_service_packages_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_service_packages_active ON public.service_packages USING btree (is_active);


--
-- Name: idx_service_packages_order; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_service_packages_order ON public.service_packages USING btree (display_order);


--
-- Name: idx_service_templates_name; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_service_templates_name ON public.huawei_service_templates USING btree (name);


--
-- Name: idx_signal_history_onu_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_signal_history_onu_id ON public.onu_signal_history USING btree (onu_id);


--
-- Name: idx_signal_history_onu_time; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_signal_history_onu_time ON public.onu_signal_history USING btree (onu_id, recorded_at DESC);


--
-- Name: idx_signal_history_recorded_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_signal_history_recorded_at ON public.onu_signal_history USING btree (recorded_at);


--
-- Name: idx_subscription_addons_addon; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_subscription_addons_addon ON public.radius_subscription_addons USING btree (addon_id);


--
-- Name: idx_subscription_addons_sub; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_subscription_addons_sub ON public.radius_subscription_addons USING btree (subscription_id);


--
-- Name: idx_subscription_devices_mac; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_subscription_devices_mac ON public.radius_subscription_devices USING btree (mac_address);


--
-- Name: idx_subscription_devices_sub; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_subscription_devices_sub ON public.radius_subscription_devices USING btree (subscription_id);


--
-- Name: idx_subscription_mac_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX IF NOT EXISTS idx_subscription_mac_unique ON public.radius_subscription_macs USING btree (subscription_id, mac_address);


--
-- Name: idx_subscription_macs_mac; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_subscription_macs_mac ON public.radius_subscription_macs USING btree (mac_address);


--
-- Name: idx_subscription_macs_single_primary; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX IF NOT EXISTS idx_subscription_macs_single_primary ON public.radius_subscription_macs USING btree (subscription_id) WHERE (is_primary = true);


--
-- Name: idx_subscription_macs_sub_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_subscription_macs_sub_id ON public.radius_subscription_macs USING btree (subscription_id);


--
-- Name: idx_subscription_macs_subscription; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_subscription_macs_subscription ON public.radius_subscription_macs USING btree (subscription_id);


--
-- Name: idx_subscriptions_license; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_subscriptions_license ON public.license_subscriptions USING btree (license_id);


--
-- Name: idx_team_members_employee_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_team_members_employee_id ON public.team_members USING btree (employee_id);


--
-- Name: idx_team_members_team_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_team_members_team_id ON public.team_members USING btree (team_id);


--
-- Name: idx_teams_branch; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_teams_branch ON public.teams USING btree (branch_id);


--
-- Name: idx_ticket_service_fees_ticket; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_ticket_service_fees_ticket ON public.ticket_service_fees USING btree (ticket_id);


--
-- Name: idx_ticket_status_tokens_expires; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_ticket_status_tokens_expires ON public.ticket_status_tokens USING btree (expires_at);


--
-- Name: idx_ticket_status_tokens_hash; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_ticket_status_tokens_hash ON public.ticket_status_tokens USING btree (token_hash);


--
-- Name: idx_ticket_status_tokens_lookup; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_ticket_status_tokens_lookup ON public.ticket_status_tokens USING btree (token_lookup);


--
-- Name: idx_ticket_status_tokens_ticket; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_ticket_status_tokens_ticket ON public.ticket_status_tokens USING btree (ticket_id);


--
-- Name: idx_ticket_templates_category; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_ticket_templates_category ON public.ticket_templates USING btree (category);


--
-- Name: idx_tickets_assigned; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_tickets_assigned ON public.tickets USING btree (assigned_to);


--
-- Name: idx_tickets_branch; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_tickets_branch ON public.tickets USING btree (branch_id);


--
-- Name: idx_tickets_created_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_tickets_created_at ON public.tickets USING btree (created_at);


--
-- Name: idx_tickets_customer; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_tickets_customer ON public.tickets USING btree (customer_id);


--
-- Name: idx_tickets_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_tickets_status ON public.tickets USING btree (status);


--
-- Name: idx_tickets_team_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_tickets_team_id ON public.tickets USING btree (team_id);


--
-- Name: idx_tr069_logs_created; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_tr069_logs_created ON public.huawei_onu_tr069_logs USING btree (created_at DESC);


--
-- Name: idx_tr069_logs_onu; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_tr069_logs_onu ON public.huawei_onu_tr069_logs USING btree (onu_id);


--
-- Name: idx_uptime_log_onu; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_uptime_log_onu ON public.onu_uptime_log USING btree (onu_id, started_at DESC);


--
-- Name: idx_validation_logs_created; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_validation_logs_created ON public.license_validation_logs USING btree (created_at);


--
-- Name: idx_validation_logs_license; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_validation_logs_license ON public.license_validation_logs USING btree (license_id);


--
-- Name: idx_vendor_bills_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_vendor_bills_status ON public.vendor_bills USING btree (status);


--
-- Name: idx_vendor_bills_vendor; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_vendor_bills_vendor ON public.vendor_bills USING btree (vendor_id);


--
-- Name: idx_vendor_payments_bill; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_vendor_payments_bill ON public.vendor_payments USING btree (bill_id);


--
-- Name: idx_vlan_history_time; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_vlan_history_time ON public.vlan_history USING btree (vlan_record_id, recorded_at);


--
-- Name: idx_vlan_traffic_recorded_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_vlan_traffic_recorded_at ON public.vlan_traffic_history USING btree (recorded_at);


--
-- Name: idx_vlan_traffic_vlan_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_vlan_traffic_vlan_id ON public.vlan_traffic_history USING btree (vlan_id);


--
-- Name: idx_whatsapp_logs_sent; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_whatsapp_logs_sent ON public.whatsapp_logs USING btree (sent_at DESC);


--
-- Name: idx_whatsapp_logs_ticket; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_whatsapp_logs_ticket ON public.whatsapp_logs USING btree (ticket_id);


--
-- Name: idx_wireguard_subnets_peer; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX IF NOT EXISTS idx_wireguard_subnets_peer ON public.wireguard_subnets USING btree (vpn_peer_id);


--
-- Name: activity_logs activity_logs_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.activity_logs ADD CONSTRAINT activity_logs_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: announcement_recipients announcement_recipients_announcement_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.announcement_recipients ADD CONSTRAINT announcement_recipients_announcement_id_fkey FOREIGN KEY (announcement_id) REFERENCES public.announcements(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: announcement_recipients announcement_recipients_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.announcement_recipients ADD CONSTRAINT announcement_recipients_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: announcements announcements_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.announcements ADD CONSTRAINT announcements_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: announcements announcements_target_branch_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.announcements ADD CONSTRAINT announcements_target_branch_id_fkey FOREIGN KEY (target_branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: announcements announcements_target_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.announcements ADD CONSTRAINT announcements_target_team_id_fkey FOREIGN KEY (target_team_id) REFERENCES public.teams(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: attendance attendance_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.attendance ADD CONSTRAINT attendance_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: attendance_notification_logs attendance_notification_logs_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.attendance_notification_logs ADD CONSTRAINT attendance_notification_logs_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: attendance_notification_logs attendance_notification_logs_notification_template_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.attendance_notification_logs ADD CONSTRAINT attendance_notification_logs_notification_template_id_fkey FOREIGN KEY (notification_template_id) REFERENCES public.hr_notification_templates(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: bill_reminders bill_reminders_bill_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.bill_reminders ADD CONSTRAINT bill_reminders_bill_id_fkey FOREIGN KEY (bill_id) REFERENCES public.vendor_bills(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: bill_reminders bill_reminders_sent_to_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.bill_reminders ADD CONSTRAINT bill_reminders_sent_to_fkey FOREIGN KEY (sent_to) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: branch_employees branch_employees_branch_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.branch_employees ADD CONSTRAINT branch_employees_branch_id_fkey FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: branch_employees branch_employees_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.branch_employees ADD CONSTRAINT branch_employees_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: branches branches_manager_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.branches ADD CONSTRAINT branches_manager_id_fkey FOREIGN KEY (manager_id) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_inbound_routes call_center_inbound_routes_trunk_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_inbound_routes ADD CONSTRAINT call_center_inbound_routes_trunk_id_fkey FOREIGN KEY (trunk_id) REFERENCES public.call_center_trunks(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_ivr_options call_center_ivr_options_ivr_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_ivr_options ADD CONSTRAINT call_center_ivr_options_ivr_id_fkey FOREIGN KEY (ivr_id) REFERENCES public.call_center_ivr(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_outbound_routes call_center_outbound_routes_trunk_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_outbound_routes ADD CONSTRAINT call_center_outbound_routes_trunk_id_fkey FOREIGN KEY (trunk_id) REFERENCES public.call_center_trunks(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_ring_group_members call_center_ring_group_members_extension_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_ring_group_members ADD CONSTRAINT call_center_ring_group_members_extension_id_fkey FOREIGN KEY (extension_id) REFERENCES public.call_center_extensions(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: call_center_ring_group_members call_center_ring_group_members_ring_group_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.call_center_ring_group_members ADD CONSTRAINT call_center_ring_group_members_ring_group_id_fkey FOREIGN KEY (ring_group_id) REFERENCES public.call_center_ring_groups(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: chart_of_accounts chart_of_accounts_parent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.chart_of_accounts ADD CONSTRAINT chart_of_accounts_parent_id_fkey FOREIGN KEY (parent_id) REFERENCES public.chart_of_accounts(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: complaints complaints_converted_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.complaints ADD CONSTRAINT complaints_converted_ticket_id_fkey FOREIGN KEY (converted_ticket_id) REFERENCES public.tickets(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: complaints complaints_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.complaints ADD CONSTRAINT complaints_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: complaints complaints_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.complaints ADD CONSTRAINT complaints_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: complaints complaints_reviewed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.complaints ADD CONSTRAINT complaints_reviewed_by_fkey FOREIGN KEY (reviewed_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: customer_payments customer_payments_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.customer_payments ADD CONSTRAINT customer_payments_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: customer_payments customer_payments_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.customer_payments ADD CONSTRAINT customer_payments_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: customer_payments customer_payments_invoice_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.customer_payments ADD CONSTRAINT customer_payments_invoice_id_fkey FOREIGN KEY (invoice_id) REFERENCES public.invoices(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: customer_payments customer_payments_mpesa_transaction_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.customer_payments ADD CONSTRAINT customer_payments_mpesa_transaction_id_fkey FOREIGN KEY (mpesa_transaction_id) REFERENCES public.mpesa_transactions(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: customer_ticket_tokens customer_ticket_tokens_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.customer_ticket_tokens ADD CONSTRAINT customer_ticket_tokens_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: customer_ticket_tokens customer_ticket_tokens_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.customer_ticket_tokens ADD CONSTRAINT customer_ticket_tokens_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: customers customers_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.customers ADD CONSTRAINT customers_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: device_interfaces device_interfaces_device_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.device_interfaces ADD CONSTRAINT device_interfaces_device_id_fkey FOREIGN KEY (device_id) REFERENCES public.network_devices(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: device_monitoring_log device_monitoring_log_device_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.device_monitoring_log ADD CONSTRAINT device_monitoring_log_device_id_fkey FOREIGN KEY (device_id) REFERENCES public.network_devices(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: device_onus device_onus_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.device_onus ADD CONSTRAINT device_onus_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: device_onus device_onus_device_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.device_onus ADD CONSTRAINT device_onus_device_id_fkey FOREIGN KEY (device_id) REFERENCES public.network_devices(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: device_vlans device_vlans_device_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.device_vlans ADD CONSTRAINT device_vlans_device_id_fkey FOREIGN KEY (device_id) REFERENCES public.network_devices(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: employee_branches employee_branches_assigned_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.employee_branches ADD CONSTRAINT employee_branches_assigned_by_fkey FOREIGN KEY (assigned_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: employee_branches employee_branches_branch_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.employee_branches ADD CONSTRAINT employee_branches_branch_id_fkey FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: employee_branches employee_branches_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.employee_branches ADD CONSTRAINT employee_branches_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: employee_kyc_documents employee_kyc_documents_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.employee_kyc_documents ADD CONSTRAINT employee_kyc_documents_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: employee_kyc_documents employee_kyc_documents_verified_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.employee_kyc_documents ADD CONSTRAINT employee_kyc_documents_verified_by_fkey FOREIGN KEY (verified_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: employees employees_department_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.employees ADD CONSTRAINT employees_department_id_fkey FOREIGN KEY (department_id) REFERENCES public.departments(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: employees employees_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.employees ADD CONSTRAINT employees_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: equipment_assignments equipment_assignments_assigned_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment_assignments ADD CONSTRAINT equipment_assignments_assigned_by_fkey FOREIGN KEY (assigned_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: equipment_assignments equipment_assignments_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment_assignments ADD CONSTRAINT equipment_assignments_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: equipment_assignments equipment_assignments_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment_assignments ADD CONSTRAINT equipment_assignments_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: equipment_assignments equipment_assignments_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment_assignments ADD CONSTRAINT equipment_assignments_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: equipment_categories equipment_categories_parent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment_categories ADD CONSTRAINT equipment_categories_parent_id_fkey FOREIGN KEY (parent_id) REFERENCES public.equipment_categories(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: equipment equipment_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment ADD CONSTRAINT equipment_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.equipment_categories(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: equipment_faults equipment_faults_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment_faults ADD CONSTRAINT equipment_faults_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: equipment_faults equipment_faults_reported_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment_faults ADD CONSTRAINT equipment_faults_reported_by_fkey FOREIGN KEY (reported_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: equipment equipment_installed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment ADD CONSTRAINT equipment_installed_by_fkey FOREIGN KEY (installed_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: equipment equipment_installed_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment ADD CONSTRAINT equipment_installed_customer_id_fkey FOREIGN KEY (installed_customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: equipment_lifecycle_logs equipment_lifecycle_logs_changed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment_lifecycle_logs ADD CONSTRAINT equipment_lifecycle_logs_changed_by_fkey FOREIGN KEY (changed_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: equipment_lifecycle_logs equipment_lifecycle_logs_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment_lifecycle_logs ADD CONSTRAINT equipment_lifecycle_logs_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: equipment_loans equipment_loans_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment_loans ADD CONSTRAINT equipment_loans_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: equipment_loans equipment_loans_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment_loans ADD CONSTRAINT equipment_loans_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: equipment_loans equipment_loans_loaned_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment_loans ADD CONSTRAINT equipment_loans_loaned_by_fkey FOREIGN KEY (loaned_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: equipment equipment_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment ADD CONSTRAINT equipment_location_id_fkey FOREIGN KEY (location_id) REFERENCES public.inventory_locations(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: equipment equipment_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.equipment ADD CONSTRAINT equipment_warehouse_id_fkey FOREIGN KEY (warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: expense_categories expense_categories_account_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.expense_categories ADD CONSTRAINT expense_categories_account_id_fkey FOREIGN KEY (account_id) REFERENCES public.chart_of_accounts(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: expenses expenses_approved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.expenses ADD CONSTRAINT expenses_approved_by_fkey FOREIGN KEY (approved_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: expenses expenses_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.expenses ADD CONSTRAINT expenses_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.expense_categories(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: expenses expenses_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.expenses ADD CONSTRAINT expenses_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: expenses expenses_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.expenses ADD CONSTRAINT expenses_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: expenses expenses_vendor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.expenses ADD CONSTRAINT expenses_vendor_id_fkey FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: departments fk_manager; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.departments ADD CONSTRAINT fk_manager FOREIGN KEY (manager_id) REFERENCES public.employees(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_alerts huawei_alerts_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_alerts ADD CONSTRAINT huawei_alerts_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_alerts huawei_alerts_onu_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_alerts ADD CONSTRAINT huawei_alerts_onu_id_fkey FOREIGN KEY (onu_id) REFERENCES public.huawei_onus(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_apartments huawei_apartments_subzone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_apartments ADD CONSTRAINT huawei_apartments_subzone_id_fkey FOREIGN KEY (subzone_id) REFERENCES public.huawei_subzones(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_apartments huawei_apartments_zone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_apartments ADD CONSTRAINT huawei_apartments_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES public.huawei_zones(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_odb_units huawei_odb_units_apartment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_odb_units ADD CONSTRAINT huawei_odb_units_apartment_id_fkey FOREIGN KEY (apartment_id) REFERENCES public.huawei_apartments(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_odb_units huawei_odb_units_subzone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_odb_units ADD CONSTRAINT huawei_odb_units_subzone_id_fkey FOREIGN KEY (subzone_id) REFERENCES public.huawei_subzones(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_odb_units huawei_odb_units_zone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_odb_units ADD CONSTRAINT huawei_odb_units_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES public.huawei_zones(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olt_boards huawei_olt_boards_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olt_boards ADD CONSTRAINT huawei_olt_boards_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olt_pon_ports huawei_olt_pon_ports_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olt_pon_ports ADD CONSTRAINT huawei_olt_pon_ports_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olt_uplinks huawei_olt_uplinks_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olt_uplinks ADD CONSTRAINT huawei_olt_uplinks_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olt_vlans huawei_olt_vlans_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olt_vlans ADD CONSTRAINT huawei_olt_vlans_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_olts huawei_olts_branch_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_olts ADD CONSTRAINT huawei_olts_branch_id_fkey FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onu_mgmt_ips huawei_onu_mgmt_ips_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onu_mgmt_ips ADD CONSTRAINT huawei_onu_mgmt_ips_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onu_mgmt_ips huawei_onu_mgmt_ips_onu_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onu_mgmt_ips ADD CONSTRAINT huawei_onu_mgmt_ips_onu_id_fkey FOREIGN KEY (onu_id) REFERENCES public.huawei_onus(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onu_service_vlans huawei_onu_service_vlans_onu_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onu_service_vlans ADD CONSTRAINT huawei_onu_service_vlans_onu_id_fkey FOREIGN KEY (onu_id) REFERENCES public.huawei_onus(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onu_tr069_logs huawei_onu_tr069_logs_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onu_tr069_logs ADD CONSTRAINT huawei_onu_tr069_logs_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onu_tr069_logs huawei_onu_tr069_logs_onu_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onu_tr069_logs ADD CONSTRAINT huawei_onu_tr069_logs_onu_id_fkey FOREIGN KEY (onu_id) REFERENCES public.huawei_onus(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onus huawei_onus_apartment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onus ADD CONSTRAINT huawei_onus_apartment_id_fkey FOREIGN KEY (apartment_id) REFERENCES public.huawei_apartments(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onus huawei_onus_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onus ADD CONSTRAINT huawei_onus_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onus huawei_onus_odb_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onus ADD CONSTRAINT huawei_onus_odb_id_fkey FOREIGN KEY (odb_id) REFERENCES public.huawei_odb_units(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onus huawei_onus_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onus ADD CONSTRAINT huawei_onus_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onus huawei_onus_onu_type_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onus ADD CONSTRAINT huawei_onus_onu_type_id_fkey FOREIGN KEY (onu_type_id) REFERENCES public.huawei_onu_types(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onus huawei_onus_service_profile_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onus ADD CONSTRAINT huawei_onus_service_profile_id_fkey FOREIGN KEY (service_profile_id) REFERENCES public.huawei_service_profiles(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onus huawei_onus_subzone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onus ADD CONSTRAINT huawei_onus_subzone_id_fkey FOREIGN KEY (subzone_id) REFERENCES public.huawei_subzones(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_onus huawei_onus_zone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_onus ADD CONSTRAINT huawei_onus_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES public.huawei_zones(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_port_vlans huawei_port_vlans_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_port_vlans ADD CONSTRAINT huawei_port_vlans_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_provisioning_logs huawei_provisioning_logs_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_provisioning_logs ADD CONSTRAINT huawei_provisioning_logs_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_provisioning_logs huawei_provisioning_logs_onu_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_provisioning_logs ADD CONSTRAINT huawei_provisioning_logs_onu_id_fkey FOREIGN KEY (onu_id) REFERENCES public.huawei_onus(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_subzones huawei_subzones_zone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_subzones ADD CONSTRAINT huawei_subzones_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES public.huawei_zones(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: huawei_vlans huawei_vlans_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.huawei_vlans ADD CONSTRAINT huawei_vlans_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: interface_history interface_history_interface_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.interface_history ADD CONSTRAINT interface_history_interface_id_fkey FOREIGN KEY (interface_id) REFERENCES public.device_interfaces(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_audit_items inventory_audit_items_audit_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_audit_items ADD CONSTRAINT inventory_audit_items_audit_id_fkey FOREIGN KEY (audit_id) REFERENCES public.inventory_audits(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_audit_items inventory_audit_items_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_audit_items ADD CONSTRAINT inventory_audit_items_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.equipment_categories(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_audit_items inventory_audit_items_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_audit_items ADD CONSTRAINT inventory_audit_items_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_audit_items inventory_audit_items_verified_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_audit_items ADD CONSTRAINT inventory_audit_items_verified_by_fkey FOREIGN KEY (verified_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_audits inventory_audits_completed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_audits ADD CONSTRAINT inventory_audits_completed_by_fkey FOREIGN KEY (completed_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_audits inventory_audits_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_audits ADD CONSTRAINT inventory_audits_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_audits inventory_audits_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_audits ADD CONSTRAINT inventory_audits_warehouse_id_fkey FOREIGN KEY (warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_locations inventory_locations_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_locations ADD CONSTRAINT inventory_locations_warehouse_id_fkey FOREIGN KEY (warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_loss_reports inventory_loss_reports_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_loss_reports ADD CONSTRAINT inventory_loss_reports_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_loss_reports inventory_loss_reports_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_loss_reports ADD CONSTRAINT inventory_loss_reports_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_loss_reports inventory_loss_reports_reported_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_loss_reports ADD CONSTRAINT inventory_loss_reports_reported_by_fkey FOREIGN KEY (reported_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_loss_reports inventory_loss_reports_resolved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_loss_reports ADD CONSTRAINT inventory_loss_reports_resolved_by_fkey FOREIGN KEY (resolved_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_po_items inventory_po_items_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_po_items ADD CONSTRAINT inventory_po_items_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.equipment_categories(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_po_items inventory_po_items_po_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_po_items ADD CONSTRAINT inventory_po_items_po_id_fkey FOREIGN KEY (po_id) REFERENCES public.inventory_purchase_orders(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_purchase_orders inventory_purchase_orders_approved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_purchase_orders ADD CONSTRAINT inventory_purchase_orders_approved_by_fkey FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_purchase_orders inventory_purchase_orders_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_purchase_orders ADD CONSTRAINT inventory_purchase_orders_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_receipt_items inventory_receipt_items_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_receipt_items ADD CONSTRAINT inventory_receipt_items_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.equipment_categories(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_receipt_items inventory_receipt_items_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_receipt_items ADD CONSTRAINT inventory_receipt_items_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_receipt_items inventory_receipt_items_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_receipt_items ADD CONSTRAINT inventory_receipt_items_location_id_fkey FOREIGN KEY (location_id) REFERENCES public.inventory_locations(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_receipt_items inventory_receipt_items_po_item_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_receipt_items ADD CONSTRAINT inventory_receipt_items_po_item_id_fkey FOREIGN KEY (po_item_id) REFERENCES public.inventory_po_items(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_receipt_items inventory_receipt_items_receipt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_receipt_items ADD CONSTRAINT inventory_receipt_items_receipt_id_fkey FOREIGN KEY (receipt_id) REFERENCES public.inventory_receipts(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_receipts inventory_receipts_po_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_receipts ADD CONSTRAINT inventory_receipts_po_id_fkey FOREIGN KEY (po_id) REFERENCES public.inventory_purchase_orders(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_receipts inventory_receipts_received_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_receipts ADD CONSTRAINT inventory_receipts_received_by_fkey FOREIGN KEY (received_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_receipts inventory_receipts_verified_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_receipts ADD CONSTRAINT inventory_receipts_verified_by_fkey FOREIGN KEY (verified_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_receipts inventory_receipts_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_receipts ADD CONSTRAINT inventory_receipts_warehouse_id_fkey FOREIGN KEY (warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_return_items inventory_return_items_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_return_items ADD CONSTRAINT inventory_return_items_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_return_items inventory_return_items_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_return_items ADD CONSTRAINT inventory_return_items_location_id_fkey FOREIGN KEY (location_id) REFERENCES public.inventory_locations(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_return_items inventory_return_items_request_item_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_return_items ADD CONSTRAINT inventory_return_items_request_item_id_fkey FOREIGN KEY (request_item_id) REFERENCES public.inventory_stock_request_items(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_return_items inventory_return_items_return_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_return_items ADD CONSTRAINT inventory_return_items_return_id_fkey FOREIGN KEY (return_id) REFERENCES public.inventory_returns(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_returns inventory_returns_received_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_returns ADD CONSTRAINT inventory_returns_received_by_fkey FOREIGN KEY (received_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_returns inventory_returns_request_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_returns ADD CONSTRAINT inventory_returns_request_id_fkey FOREIGN KEY (request_id) REFERENCES public.inventory_stock_requests(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_returns inventory_returns_returned_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_returns ADD CONSTRAINT inventory_returns_returned_by_fkey FOREIGN KEY (returned_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_returns inventory_returns_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_returns ADD CONSTRAINT inventory_returns_warehouse_id_fkey FOREIGN KEY (warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_rma inventory_rma_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_rma ADD CONSTRAINT inventory_rma_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_rma inventory_rma_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_rma ADD CONSTRAINT inventory_rma_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_rma inventory_rma_fault_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_rma ADD CONSTRAINT inventory_rma_fault_id_fkey FOREIGN KEY (fault_id) REFERENCES public.equipment_faults(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_rma inventory_rma_replacement_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_rma ADD CONSTRAINT inventory_rma_replacement_equipment_id_fkey FOREIGN KEY (replacement_equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_levels inventory_stock_levels_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_levels ADD CONSTRAINT inventory_stock_levels_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.equipment_categories(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_levels inventory_stock_levels_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_levels ADD CONSTRAINT inventory_stock_levels_warehouse_id_fkey FOREIGN KEY (warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_movements inventory_stock_movements_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_movements ADD CONSTRAINT inventory_stock_movements_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_movements inventory_stock_movements_from_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_movements ADD CONSTRAINT inventory_stock_movements_from_location_id_fkey FOREIGN KEY (from_location_id) REFERENCES public.inventory_locations(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_movements inventory_stock_movements_from_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_movements ADD CONSTRAINT inventory_stock_movements_from_warehouse_id_fkey FOREIGN KEY (from_warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_movements inventory_stock_movements_performed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_movements ADD CONSTRAINT inventory_stock_movements_performed_by_fkey FOREIGN KEY (performed_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_movements inventory_stock_movements_to_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_movements ADD CONSTRAINT inventory_stock_movements_to_location_id_fkey FOREIGN KEY (to_location_id) REFERENCES public.inventory_locations(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_movements inventory_stock_movements_to_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_movements ADD CONSTRAINT inventory_stock_movements_to_warehouse_id_fkey FOREIGN KEY (to_warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_request_items inventory_stock_request_items_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_request_items ADD CONSTRAINT inventory_stock_request_items_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.equipment_categories(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_request_items inventory_stock_request_items_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_request_items ADD CONSTRAINT inventory_stock_request_items_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_request_items inventory_stock_request_items_request_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_request_items ADD CONSTRAINT inventory_stock_request_items_request_id_fkey FOREIGN KEY (request_id) REFERENCES public.inventory_stock_requests(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_requests inventory_stock_requests_approved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_requests ADD CONSTRAINT inventory_stock_requests_approved_by_fkey FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_requests inventory_stock_requests_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_requests ADD CONSTRAINT inventory_stock_requests_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_requests inventory_stock_requests_handed_to_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_requests ADD CONSTRAINT inventory_stock_requests_handed_to_fkey FOREIGN KEY (handed_to) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_requests inventory_stock_requests_picked_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_requests ADD CONSTRAINT inventory_stock_requests_picked_by_fkey FOREIGN KEY (picked_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_requests inventory_stock_requests_requested_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_requests ADD CONSTRAINT inventory_stock_requests_requested_by_fkey FOREIGN KEY (requested_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_requests inventory_stock_requests_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_requests ADD CONSTRAINT inventory_stock_requests_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_stock_requests inventory_stock_requests_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_stock_requests ADD CONSTRAINT inventory_stock_requests_warehouse_id_fkey FOREIGN KEY (warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_thresholds inventory_thresholds_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_thresholds ADD CONSTRAINT inventory_thresholds_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.equipment_categories(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_thresholds inventory_thresholds_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_thresholds ADD CONSTRAINT inventory_thresholds_warehouse_id_fkey FOREIGN KEY (warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_usage inventory_usage_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_usage ADD CONSTRAINT inventory_usage_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_usage inventory_usage_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_usage ADD CONSTRAINT inventory_usage_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_usage inventory_usage_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_usage ADD CONSTRAINT inventory_usage_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_usage inventory_usage_recorded_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_usage ADD CONSTRAINT inventory_usage_recorded_by_fkey FOREIGN KEY (recorded_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_usage inventory_usage_request_item_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_usage ADD CONSTRAINT inventory_usage_request_item_id_fkey FOREIGN KEY (request_item_id) REFERENCES public.inventory_stock_request_items(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_usage inventory_usage_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_usage ADD CONSTRAINT inventory_usage_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: inventory_warehouses inventory_warehouses_manager_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.inventory_warehouses ADD CONSTRAINT inventory_warehouses_manager_id_fkey FOREIGN KEY (manager_id) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: invoice_items invoice_items_invoice_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.invoice_items ADD CONSTRAINT invoice_items_invoice_id_fkey FOREIGN KEY (invoice_id) REFERENCES public.invoices(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: invoice_items invoice_items_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.invoice_items ADD CONSTRAINT invoice_items_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.products_services(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: invoice_items invoice_items_tax_rate_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.invoice_items ADD CONSTRAINT invoice_items_tax_rate_id_fkey FOREIGN KEY (tax_rate_id) REFERENCES public.tax_rates(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: invoices invoices_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.invoices ADD CONSTRAINT invoices_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: invoices invoices_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.invoices ADD CONSTRAINT invoices_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: invoices invoices_order_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.invoices ADD CONSTRAINT invoices_order_id_fkey FOREIGN KEY (order_id) REFERENCES public.orders(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: invoices invoices_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.invoices ADD CONSTRAINT invoices_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: isp_sub_locations isp_sub_locations_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.isp_sub_locations ADD CONSTRAINT isp_sub_locations_location_id_fkey FOREIGN KEY (location_id) REFERENCES public.isp_locations(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: leave_balances leave_balances_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.leave_balances ADD CONSTRAINT leave_balances_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: leave_balances leave_balances_leave_type_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.leave_balances ADD CONSTRAINT leave_balances_leave_type_id_fkey FOREIGN KEY (leave_type_id) REFERENCES public.leave_types(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: leave_calendar leave_calendar_branch_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.leave_calendar ADD CONSTRAINT leave_calendar_branch_id_fkey FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: leave_requests leave_requests_approved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.leave_requests ADD CONSTRAINT leave_requests_approved_by_fkey FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: leave_requests leave_requests_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.leave_requests ADD CONSTRAINT leave_requests_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: leave_requests leave_requests_leave_type_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.leave_requests ADD CONSTRAINT leave_requests_leave_type_id_fkey FOREIGN KEY (leave_type_id) REFERENCES public.leave_types(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: license_activations license_activations_license_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_activations ADD CONSTRAINT license_activations_license_id_fkey FOREIGN KEY (license_id) REFERENCES public.licenses(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: license_payments license_payments_license_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_payments ADD CONSTRAINT license_payments_license_id_fkey FOREIGN KEY (license_id) REFERENCES public.licenses(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: license_payments license_payments_subscription_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_payments ADD CONSTRAINT license_payments_subscription_id_fkey FOREIGN KEY (subscription_id) REFERENCES public.license_subscriptions(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: license_subscriptions license_subscriptions_license_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_subscriptions ADD CONSTRAINT license_subscriptions_license_id_fkey FOREIGN KEY (license_id) REFERENCES public.licenses(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: license_subscriptions license_subscriptions_tier_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_subscriptions ADD CONSTRAINT license_subscriptions_tier_id_fkey FOREIGN KEY (tier_id) REFERENCES public.license_tiers(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: license_tiers license_tiers_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_tiers ADD CONSTRAINT license_tiers_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.license_products(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: license_validation_logs license_validation_logs_activation_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_validation_logs ADD CONSTRAINT license_validation_logs_activation_id_fkey FOREIGN KEY (activation_id) REFERENCES public.license_activations(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: license_validation_logs license_validation_logs_license_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.license_validation_logs ADD CONSTRAINT license_validation_logs_license_id_fkey FOREIGN KEY (license_id) REFERENCES public.licenses(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: licenses licenses_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.licenses ADD CONSTRAINT licenses_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.license_customers(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: licenses licenses_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.licenses ADD CONSTRAINT licenses_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.license_products(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: licenses licenses_tier_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.licenses ADD CONSTRAINT licenses_tier_id_fkey FOREIGN KEY (tier_id) REFERENCES public.license_tiers(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: mikrotik_provisioned_ips mikrotik_provisioned_ips_nas_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mikrotik_provisioned_ips ADD CONSTRAINT mikrotik_provisioned_ips_nas_id_fkey FOREIGN KEY (nas_id) REFERENCES public.radius_nas(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: mikrotik_provisioned_ips mikrotik_provisioned_ips_subscription_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mikrotik_provisioned_ips ADD CONSTRAINT mikrotik_provisioned_ips_subscription_id_fkey FOREIGN KEY (subscription_id) REFERENCES public.radius_subscriptions(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: mikrotik_provisioned_ips mikrotik_provisioned_ips_vlan_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mikrotik_provisioned_ips ADD CONSTRAINT mikrotik_provisioned_ips_vlan_id_fkey FOREIGN KEY (vlan_id) REFERENCES public.mikrotik_vlans(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: mikrotik_vlans mikrotik_vlans_nas_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mikrotik_vlans ADD CONSTRAINT mikrotik_vlans_nas_id_fkey FOREIGN KEY (nas_id) REFERENCES public.radius_nas(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: mobile_notifications mobile_notifications_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mobile_notifications ADD CONSTRAINT mobile_notifications_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: mobile_tokens mobile_tokens_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mobile_tokens ADD CONSTRAINT mobile_tokens_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: mpesa_b2b_transactions mpesa_b2b_transactions_initiated_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mpesa_b2b_transactions ADD CONSTRAINT mpesa_b2b_transactions_initiated_by_fkey FOREIGN KEY (initiated_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: mpesa_b2c_transactions mpesa_b2c_transactions_initiated_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mpesa_b2c_transactions ADD CONSTRAINT mpesa_b2c_transactions_initiated_by_fkey FOREIGN KEY (initiated_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: mpesa_c2b_transactions mpesa_c2b_transactions_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mpesa_c2b_transactions ADD CONSTRAINT mpesa_c2b_transactions_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: mpesa_transactions mpesa_transactions_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.mpesa_transactions ADD CONSTRAINT mpesa_transactions_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: onu_discovery_log onu_discovery_log_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.onu_discovery_log ADD CONSTRAINT onu_discovery_log_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: onu_discovery_log onu_discovery_log_onu_type_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.onu_discovery_log ADD CONSTRAINT onu_discovery_log_onu_type_id_fkey FOREIGN KEY (onu_type_id) REFERENCES public.huawei_onu_types(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: onu_fault_log onu_fault_log_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.onu_fault_log ADD CONSTRAINT onu_fault_log_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: onu_fault_log onu_fault_log_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.onu_fault_log ADD CONSTRAINT onu_fault_log_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: onu_signal_history onu_signal_history_onu_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.onu_signal_history ADD CONSTRAINT onu_signal_history_onu_id_fkey FOREIGN KEY (onu_id) REFERENCES public.huawei_onus(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: onu_uptime_log onu_uptime_log_onu_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.onu_uptime_log ADD CONSTRAINT onu_uptime_log_onu_id_fkey FOREIGN KEY (onu_id) REFERENCES public.huawei_onus(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: orders orders_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.orders ADD CONSTRAINT orders_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: orders orders_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.orders ADD CONSTRAINT orders_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: orders orders_mpesa_transaction_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.orders ADD CONSTRAINT orders_mpesa_transaction_id_fkey FOREIGN KEY (mpesa_transaction_id) REFERENCES public.mpesa_transactions(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: orders orders_package_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.orders ADD CONSTRAINT orders_package_id_fkey FOREIGN KEY (package_id) REFERENCES public.service_packages(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: orders orders_salesperson_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.orders ADD CONSTRAINT orders_salesperson_id_fkey FOREIGN KEY (salesperson_id) REFERENCES public.salespersons(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: orders orders_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.orders ADD CONSTRAINT orders_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: payroll_commissions payroll_commissions_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.payroll_commissions ADD CONSTRAINT payroll_commissions_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: payroll_commissions payroll_commissions_payroll_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.payroll_commissions ADD CONSTRAINT payroll_commissions_payroll_id_fkey FOREIGN KEY (payroll_id) REFERENCES public.payroll(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: payroll payroll_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.payroll ADD CONSTRAINT payroll_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: performance_reviews performance_reviews_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.performance_reviews ADD CONSTRAINT performance_reviews_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: performance_reviews performance_reviews_reviewer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.performance_reviews ADD CONSTRAINT performance_reviews_reviewer_id_fkey FOREIGN KEY (reviewer_id) REFERENCES public.employees(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: products_services products_services_expense_account_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.products_services ADD CONSTRAINT products_services_expense_account_id_fkey FOREIGN KEY (expense_account_id) REFERENCES public.chart_of_accounts(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: products_services products_services_income_account_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.products_services ADD CONSTRAINT products_services_income_account_id_fkey FOREIGN KEY (income_account_id) REFERENCES public.chart_of_accounts(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: products_services products_services_tax_rate_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.products_services ADD CONSTRAINT products_services_tax_rate_id_fkey FOREIGN KEY (tax_rate_id) REFERENCES public.tax_rates(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: purchase_order_items purchase_order_items_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.purchase_order_items ADD CONSTRAINT purchase_order_items_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: purchase_order_items purchase_order_items_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.purchase_order_items ADD CONSTRAINT purchase_order_items_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.products_services(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: purchase_order_items purchase_order_items_purchase_order_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.purchase_order_items ADD CONSTRAINT purchase_order_items_purchase_order_id_fkey FOREIGN KEY (purchase_order_id) REFERENCES public.purchase_orders(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: purchase_order_items purchase_order_items_tax_rate_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.purchase_order_items ADD CONSTRAINT purchase_order_items_tax_rate_id_fkey FOREIGN KEY (tax_rate_id) REFERENCES public.tax_rates(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: purchase_orders purchase_orders_approved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.purchase_orders ADD CONSTRAINT purchase_orders_approved_by_fkey FOREIGN KEY (approved_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: purchase_orders purchase_orders_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.purchase_orders ADD CONSTRAINT purchase_orders_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: purchase_orders purchase_orders_vendor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.purchase_orders ADD CONSTRAINT purchase_orders_vendor_id_fkey FOREIGN KEY (vendor_id) REFERENCES public.vendors(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: quote_items quote_items_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.quote_items ADD CONSTRAINT quote_items_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.products_services(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: quote_items quote_items_quote_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.quote_items ADD CONSTRAINT quote_items_quote_id_fkey FOREIGN KEY (quote_id) REFERENCES public.quotes(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: quote_items quote_items_tax_rate_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.quote_items ADD CONSTRAINT quote_items_tax_rate_id_fkey FOREIGN KEY (tax_rate_id) REFERENCES public.tax_rates(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: quotes quotes_converted_to_invoice_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.quotes ADD CONSTRAINT quotes_converted_to_invoice_id_fkey FOREIGN KEY (converted_to_invoice_id) REFERENCES public.invoices(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: quotes quotes_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.quotes ADD CONSTRAINT quotes_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: quotes quotes_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.quotes ADD CONSTRAINT quotes_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_alert_log radius_alert_log_subscription_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_alert_log ADD CONSTRAINT radius_alert_log_subscription_id_fkey FOREIGN KEY (subscription_id) REFERENCES public.radius_subscriptions(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_auth_logs radius_auth_logs_subscription_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_auth_logs ADD CONSTRAINT radius_auth_logs_subscription_id_fkey FOREIGN KEY (subscription_id) REFERENCES public.radius_subscriptions(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_billing radius_billing_package_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_billing ADD CONSTRAINT radius_billing_package_id_fkey FOREIGN KEY (package_id) REFERENCES public.radius_packages(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_billing radius_billing_subscription_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_billing ADD CONSTRAINT radius_billing_subscription_id_fkey FOREIGN KEY (subscription_id) REFERENCES public.radius_subscriptions(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_invoices radius_invoices_subscription_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_invoices ADD CONSTRAINT radius_invoices_subscription_id_fkey FOREIGN KEY (subscription_id) REFERENCES public.radius_subscriptions(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_ip_pools radius_ip_pools_assigned_to_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_ip_pools ADD CONSTRAINT radius_ip_pools_assigned_to_fkey FOREIGN KEY (assigned_to) REFERENCES public.radius_subscriptions(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_ip_pools radius_ip_pools_nas_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_ip_pools ADD CONSTRAINT radius_ip_pools_nas_id_fkey FOREIGN KEY (nas_id) REFERENCES public.radius_nas(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_nas radius_nas_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_nas ADD CONSTRAINT radius_nas_location_id_fkey FOREIGN KEY (location_id) REFERENCES public.huawei_zones(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_nas_packages radius_nas_packages_nas_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_nas_packages ADD CONSTRAINT radius_nas_packages_nas_id_fkey FOREIGN KEY (nas_id) REFERENCES public.radius_nas(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_nas_packages radius_nas_packages_package_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_nas_packages ADD CONSTRAINT radius_nas_packages_package_id_fkey FOREIGN KEY (package_id) REFERENCES public.radius_packages(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_nas radius_nas_sub_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_nas ADD CONSTRAINT radius_nas_sub_location_id_fkey FOREIGN KEY (sub_location_id) REFERENCES public.huawei_subzones(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_nas radius_nas_wireguard_peer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_nas ADD CONSTRAINT radius_nas_wireguard_peer_id_fkey FOREIGN KEY (wireguard_peer_id) REFERENCES public.wireguard_peers(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_package_schedules radius_package_schedules_package_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_package_schedules ADD CONSTRAINT radius_package_schedules_package_id_fkey FOREIGN KEY (package_id) REFERENCES public.radius_packages(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_promotions radius_promotions_package_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_promotions ADD CONSTRAINT radius_promotions_package_id_fkey FOREIGN KEY (package_id) REFERENCES public.radius_packages(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_referrals radius_referrals_referred_subscription_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_referrals ADD CONSTRAINT radius_referrals_referred_subscription_id_fkey FOREIGN KEY (referred_subscription_id) REFERENCES public.radius_subscriptions(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_referrals radius_referrals_referrer_subscription_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_referrals ADD CONSTRAINT radius_referrals_referrer_subscription_id_fkey FOREIGN KEY (referrer_subscription_id) REFERENCES public.radius_subscriptions(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_sessions radius_sessions_nas_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_sessions ADD CONSTRAINT radius_sessions_nas_id_fkey FOREIGN KEY (nas_id) REFERENCES public.radius_nas(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_sessions radius_sessions_subscription_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_sessions ADD CONSTRAINT radius_sessions_subscription_id_fkey FOREIGN KEY (subscription_id) REFERENCES public.radius_subscriptions(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_speed_overrides radius_speed_overrides_package_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_speed_overrides ADD CONSTRAINT radius_speed_overrides_package_id_fkey FOREIGN KEY (package_id) REFERENCES public.radius_packages(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscription_addons radius_subscription_addons_addon_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscription_addons ADD CONSTRAINT radius_subscription_addons_addon_id_fkey FOREIGN KEY (addon_id) REFERENCES public.radius_addon_services(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscription_addons radius_subscription_addons_subscription_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscription_addons ADD CONSTRAINT radius_subscription_addons_subscription_id_fkey FOREIGN KEY (subscription_id) REFERENCES public.radius_subscriptions(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscription_devices radius_subscription_devices_subscription_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscription_devices ADD CONSTRAINT radius_subscription_devices_subscription_id_fkey FOREIGN KEY (subscription_id) REFERENCES public.radius_subscriptions(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscription_macs radius_subscription_macs_subscription_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscription_macs ADD CONSTRAINT radius_subscription_macs_subscription_id_fkey FOREIGN KEY (subscription_id) REFERENCES public.radius_subscriptions(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscriptions radius_subscriptions_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscriptions ADD CONSTRAINT radius_subscriptions_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscriptions radius_subscriptions_huawei_onu_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscriptions ADD CONSTRAINT radius_subscriptions_huawei_onu_id_fkey FOREIGN KEY (huawei_onu_id) REFERENCES public.huawei_onus(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscriptions radius_subscriptions_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscriptions ADD CONSTRAINT radius_subscriptions_location_id_fkey FOREIGN KEY (location_id) REFERENCES public.huawei_zones(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscriptions radius_subscriptions_nas_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscriptions ADD CONSTRAINT radius_subscriptions_nas_id_fkey FOREIGN KEY (nas_id) REFERENCES public.radius_nas(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscriptions radius_subscriptions_package_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscriptions ADD CONSTRAINT radius_subscriptions_package_id_fkey FOREIGN KEY (package_id) REFERENCES public.radius_packages(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_subscriptions radius_subscriptions_sub_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_subscriptions ADD CONSTRAINT radius_subscriptions_sub_location_id_fkey FOREIGN KEY (sub_location_id) REFERENCES public.huawei_subzones(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_usage_logs radius_usage_logs_subscription_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_usage_logs ADD CONSTRAINT radius_usage_logs_subscription_id_fkey FOREIGN KEY (subscription_id) REFERENCES public.radius_subscriptions(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_vouchers radius_vouchers_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_vouchers ADD CONSTRAINT radius_vouchers_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_vouchers radius_vouchers_package_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_vouchers ADD CONSTRAINT radius_vouchers_package_id_fkey FOREIGN KEY (package_id) REFERENCES public.radius_packages(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: radius_vouchers radius_vouchers_used_by_subscription_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.radius_vouchers ADD CONSTRAINT radius_vouchers_used_by_subscription_id_fkey FOREIGN KEY (used_by_subscription_id) REFERENCES public.radius_subscriptions(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: role_permissions role_permissions_permission_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.role_permissions ADD CONSTRAINT role_permissions_permission_id_fkey FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: role_permissions role_permissions_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.role_permissions ADD CONSTRAINT role_permissions_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: salary_advance_repayments salary_advance_repayments_advance_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.salary_advance_repayments ADD CONSTRAINT salary_advance_repayments_advance_id_fkey FOREIGN KEY (advance_id) REFERENCES public.salary_advances(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: salary_advance_repayments salary_advance_repayments_payroll_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.salary_advance_repayments ADD CONSTRAINT salary_advance_repayments_payroll_id_fkey FOREIGN KEY (payroll_id) REFERENCES public.payroll(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: salary_advances salary_advances_approved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.salary_advances ADD CONSTRAINT salary_advances_approved_by_fkey FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: salary_advances salary_advances_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.salary_advances ADD CONSTRAINT salary_advances_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: salary_advances salary_advances_mpesa_b2c_transaction_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.salary_advances ADD CONSTRAINT salary_advances_mpesa_b2c_transaction_id_fkey FOREIGN KEY (mpesa_b2c_transaction_id) REFERENCES public.mpesa_b2c_transactions(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: sales_commissions sales_commissions_order_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.sales_commissions ADD CONSTRAINT sales_commissions_order_id_fkey FOREIGN KEY (order_id) REFERENCES public.orders(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: sales_commissions sales_commissions_salesperson_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.sales_commissions ADD CONSTRAINT sales_commissions_salesperson_id_fkey FOREIGN KEY (salesperson_id) REFERENCES public.salespersons(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: salespersons salespersons_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.salespersons ADD CONSTRAINT salespersons_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: salespersons salespersons_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.salespersons ADD CONSTRAINT salespersons_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: sla_policies sla_policies_escalation_to_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.sla_policies ADD CONSTRAINT sla_policies_escalation_to_fkey FOREIGN KEY (escalation_to) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: sms_logs sms_logs_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.sms_logs ADD CONSTRAINT sms_logs_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: team_members team_members_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.team_members ADD CONSTRAINT team_members_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: team_members team_members_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.team_members ADD CONSTRAINT team_members_team_id_fkey FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: teams teams_branch_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.teams ADD CONSTRAINT teams_branch_id_fkey FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: teams teams_leader_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.teams ADD CONSTRAINT teams_leader_id_fkey FOREIGN KEY (leader_id) REFERENCES public.employees(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: technician_kit_items technician_kit_items_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.technician_kit_items ADD CONSTRAINT technician_kit_items_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.equipment_categories(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: technician_kit_items technician_kit_items_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.technician_kit_items ADD CONSTRAINT technician_kit_items_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: technician_kit_items technician_kit_items_kit_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.technician_kit_items ADD CONSTRAINT technician_kit_items_kit_id_fkey FOREIGN KEY (kit_id) REFERENCES public.technician_kits(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: technician_kits technician_kits_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.technician_kits ADD CONSTRAINT technician_kits_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: technician_kits technician_kits_issued_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.technician_kits ADD CONSTRAINT technician_kits_issued_by_fkey FOREIGN KEY (issued_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_comments ticket_comments_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_comments ADD CONSTRAINT ticket_comments_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_comments ticket_comments_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_comments ADD CONSTRAINT ticket_comments_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_earnings ticket_earnings_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_earnings ADD CONSTRAINT ticket_earnings_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_earnings ticket_earnings_payroll_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_earnings ADD CONSTRAINT ticket_earnings_payroll_id_fkey FOREIGN KEY (payroll_id) REFERENCES public.payroll(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_earnings ticket_earnings_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_earnings ADD CONSTRAINT ticket_earnings_team_id_fkey FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_earnings ticket_earnings_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_earnings ADD CONSTRAINT ticket_earnings_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_escalations ticket_escalations_escalated_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_escalations ADD CONSTRAINT ticket_escalations_escalated_by_fkey FOREIGN KEY (escalated_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_escalations ticket_escalations_escalated_to_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_escalations ADD CONSTRAINT ticket_escalations_escalated_to_fkey FOREIGN KEY (escalated_to) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_escalations ticket_escalations_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_escalations ADD CONSTRAINT ticket_escalations_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_resolution_photos ticket_resolution_photos_resolution_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_resolution_photos ADD CONSTRAINT ticket_resolution_photos_resolution_id_fkey FOREIGN KEY (resolution_id) REFERENCES public.ticket_resolutions(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_resolution_photos ticket_resolution_photos_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_resolution_photos ADD CONSTRAINT ticket_resolution_photos_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_resolution_photos ticket_resolution_photos_uploaded_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_resolution_photos ADD CONSTRAINT ticket_resolution_photos_uploaded_by_fkey FOREIGN KEY (uploaded_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_resolutions ticket_resolutions_resolved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_resolutions ADD CONSTRAINT ticket_resolutions_resolved_by_fkey FOREIGN KEY (resolved_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_resolutions ticket_resolutions_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_resolutions ADD CONSTRAINT ticket_resolutions_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_satisfaction_ratings ticket_satisfaction_ratings_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_satisfaction_ratings ADD CONSTRAINT ticket_satisfaction_ratings_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_satisfaction_ratings ticket_satisfaction_ratings_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_satisfaction_ratings ADD CONSTRAINT ticket_satisfaction_ratings_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_service_fees ticket_service_fees_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_service_fees ADD CONSTRAINT ticket_service_fees_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_service_fees ticket_service_fees_fee_type_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_service_fees ADD CONSTRAINT ticket_service_fees_fee_type_id_fkey FOREIGN KEY (fee_type_id) REFERENCES public.service_fee_types(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_service_fees ticket_service_fees_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_service_fees ADD CONSTRAINT ticket_service_fees_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_sla_logs ticket_sla_logs_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_sla_logs ADD CONSTRAINT ticket_sla_logs_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_status_tokens ticket_status_tokens_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_status_tokens ADD CONSTRAINT ticket_status_tokens_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_status_tokens ticket_status_tokens_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_status_tokens ADD CONSTRAINT ticket_status_tokens_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: ticket_templates ticket_templates_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.ticket_templates ADD CONSTRAINT ticket_templates_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: tickets tickets_assigned_to_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.tickets ADD CONSTRAINT tickets_assigned_to_fkey FOREIGN KEY (assigned_to) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: tickets tickets_branch_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.tickets ADD CONSTRAINT tickets_branch_id_fkey FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: tickets tickets_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.tickets ADD CONSTRAINT tickets_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: tickets tickets_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.tickets ADD CONSTRAINT tickets_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: tickets tickets_sla_policy_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.tickets ADD CONSTRAINT tickets_sla_policy_id_fkey FOREIGN KEY (sla_policy_id) REFERENCES public.sla_policies(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: tickets tickets_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.tickets ADD CONSTRAINT tickets_team_id_fkey FOREIGN KEY (team_id) REFERENCES public.teams(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: user_notifications user_notifications_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.user_notifications ADD CONSTRAINT user_notifications_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: users users_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.users ADD CONSTRAINT users_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: vendor_bill_items vendor_bill_items_account_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vendor_bill_items ADD CONSTRAINT vendor_bill_items_account_id_fkey FOREIGN KEY (account_id) REFERENCES public.chart_of_accounts(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: vendor_bill_items vendor_bill_items_bill_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vendor_bill_items ADD CONSTRAINT vendor_bill_items_bill_id_fkey FOREIGN KEY (bill_id) REFERENCES public.vendor_bills(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: vendor_bill_items vendor_bill_items_tax_rate_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vendor_bill_items ADD CONSTRAINT vendor_bill_items_tax_rate_id_fkey FOREIGN KEY (tax_rate_id) REFERENCES public.tax_rates(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: vendor_bills vendor_bills_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vendor_bills ADD CONSTRAINT vendor_bills_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: vendor_bills vendor_bills_purchase_order_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vendor_bills ADD CONSTRAINT vendor_bills_purchase_order_id_fkey FOREIGN KEY (purchase_order_id) REFERENCES public.purchase_orders(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: vendor_bills vendor_bills_vendor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vendor_bills ADD CONSTRAINT vendor_bills_vendor_id_fkey FOREIGN KEY (vendor_id) REFERENCES public.vendors(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: vendor_payments vendor_payments_bill_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vendor_payments ADD CONSTRAINT vendor_payments_bill_id_fkey FOREIGN KEY (bill_id) REFERENCES public.vendor_bills(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: vendor_payments vendor_payments_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vendor_payments ADD CONSTRAINT vendor_payments_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: vendor_payments vendor_payments_vendor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vendor_payments ADD CONSTRAINT vendor_payments_vendor_id_fkey FOREIGN KEY (vendor_id) REFERENCES public.vendors(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: vlan_history vlan_history_vlan_record_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vlan_history ADD CONSTRAINT vlan_history_vlan_record_id_fkey FOREIGN KEY (vlan_record_id) REFERENCES public.device_vlans(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: vlan_traffic_history vlan_traffic_history_vlan_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.vlan_traffic_history ADD CONSTRAINT vlan_traffic_history_vlan_id_fkey FOREIGN KEY (vlan_id) REFERENCES public.mikrotik_vlans(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: whatsapp_conversations whatsapp_conversations_assigned_to_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.whatsapp_conversations ADD CONSTRAINT whatsapp_conversations_assigned_to_fkey FOREIGN KEY (assigned_to) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: whatsapp_conversations whatsapp_conversations_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.whatsapp_conversations ADD CONSTRAINT whatsapp_conversations_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: whatsapp_logs whatsapp_logs_complaint_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.whatsapp_logs ADD CONSTRAINT whatsapp_logs_complaint_id_fkey FOREIGN KEY (complaint_id) REFERENCES public.complaints(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: whatsapp_logs whatsapp_logs_order_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.whatsapp_logs ADD CONSTRAINT whatsapp_logs_order_id_fkey FOREIGN KEY (order_id) REFERENCES public.orders(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: whatsapp_logs whatsapp_logs_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.whatsapp_logs ADD CONSTRAINT whatsapp_logs_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: whatsapp_messages whatsapp_messages_conversation_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.whatsapp_messages ADD CONSTRAINT whatsapp_messages_conversation_id_fkey FOREIGN KEY (conversation_id) REFERENCES public.whatsapp_conversations(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: whatsapp_messages whatsapp_messages_sent_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.whatsapp_messages ADD CONSTRAINT whatsapp_messages_sent_by_fkey FOREIGN KEY (sent_by) REFERENCES public.users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: wireguard_peers wireguard_peers_server_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.wireguard_peers ADD CONSTRAINT wireguard_peers_server_id_fkey FOREIGN KEY (server_id) REFERENCES public.wireguard_servers(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: wireguard_subnets wireguard_subnets_vpn_peer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.wireguard_subnets ADD CONSTRAINT wireguard_subnets_vpn_peer_id_fkey FOREIGN KEY (vpn_peer_id) REFERENCES public.wireguard_peers(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- Name: wireguard_sync_logs wireguard_sync_logs_server_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

DO $$ BEGIN
    ALTER TABLE ONLY public.wireguard_sync_logs ADD CONSTRAINT wireguard_sync_logs_server_id_fkey FOREIGN KEY (server_id) REFERENCES public.wireguard_servers(id);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;


--
-- PostgreSQL database dump complete
--


