--
-- PostgreSQL database dump
--

\restrict iAsDMFYLD7uyJ3mm5pO9Nh7vpObuN2D7XTZ1AAfi6ElfHP9wp7KCmemv1urdrP6

-- Dumped from database version 16.11 (74c6bb6)
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
-- Name: accounting_settings; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.accounting_settings (
    id integer NOT NULL,
    setting_key character varying(100) NOT NULL,
    setting_value text,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.accounting_settings OWNER TO neondb_owner;

--
-- Name: accounting_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.accounting_settings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.accounting_settings_id_seq OWNER TO neondb_owner;

--
-- Name: accounting_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.accounting_settings_id_seq OWNED BY public.accounting_settings.id;


--
-- Name: activity_logs; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.activity_logs (
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


ALTER TABLE public.activity_logs OWNER TO neondb_owner;

--
-- Name: activity_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.activity_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.activity_logs_id_seq OWNER TO neondb_owner;

--
-- Name: activity_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.activity_logs_id_seq OWNED BY public.activity_logs.id;


--
-- Name: announcement_recipients; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.announcement_recipients (
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


ALTER TABLE public.announcement_recipients OWNER TO neondb_owner;

--
-- Name: announcement_recipients_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.announcement_recipients_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.announcement_recipients_id_seq OWNER TO neondb_owner;

--
-- Name: announcement_recipients_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.announcement_recipients_id_seq OWNED BY public.announcement_recipients.id;


--
-- Name: announcements; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.announcements (
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


ALTER TABLE public.announcements OWNER TO neondb_owner;

--
-- Name: announcements_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.announcements_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.announcements_id_seq OWNER TO neondb_owner;

--
-- Name: announcements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.announcements_id_seq OWNED BY public.announcements.id;


--
-- Name: attendance; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.attendance (
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


ALTER TABLE public.attendance OWNER TO neondb_owner;

--
-- Name: attendance_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.attendance_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendance_id_seq OWNER TO neondb_owner;

--
-- Name: attendance_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.attendance_id_seq OWNED BY public.attendance.id;


--
-- Name: attendance_notification_logs; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.attendance_notification_logs (
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


ALTER TABLE public.attendance_notification_logs OWNER TO neondb_owner;

--
-- Name: attendance_notification_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.attendance_notification_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendance_notification_logs_id_seq OWNER TO neondb_owner;

--
-- Name: attendance_notification_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.attendance_notification_logs_id_seq OWNED BY public.attendance_notification_logs.id;


--
-- Name: bill_reminders; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.bill_reminders (
    id integer NOT NULL,
    bill_id integer NOT NULL,
    reminder_date date NOT NULL,
    sent_at timestamp without time zone,
    sent_to integer,
    notification_type character varying(20) DEFAULT 'both'::character varying,
    is_sent boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.bill_reminders OWNER TO neondb_owner;

--
-- Name: bill_reminders_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.bill_reminders_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.bill_reminders_id_seq OWNER TO neondb_owner;

--
-- Name: bill_reminders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.bill_reminders_id_seq OWNED BY public.bill_reminders.id;


--
-- Name: biometric_attendance_logs; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.biometric_attendance_logs (
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


ALTER TABLE public.biometric_attendance_logs OWNER TO neondb_owner;

--
-- Name: biometric_attendance_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.biometric_attendance_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.biometric_attendance_logs_id_seq OWNER TO neondb_owner;

--
-- Name: biometric_attendance_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.biometric_attendance_logs_id_seq OWNED BY public.biometric_attendance_logs.id;


--
-- Name: biometric_devices; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.biometric_devices (
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


ALTER TABLE public.biometric_devices OWNER TO neondb_owner;

--
-- Name: biometric_devices_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.biometric_devices_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.biometric_devices_id_seq OWNER TO neondb_owner;

--
-- Name: biometric_devices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.biometric_devices_id_seq OWNED BY public.biometric_devices.id;


--
-- Name: branch_employees; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.branch_employees (
    id integer NOT NULL,
    branch_id integer,
    employee_id integer,
    is_primary boolean DEFAULT false,
    assigned_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.branch_employees OWNER TO neondb_owner;

--
-- Name: branch_employees_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.branch_employees_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.branch_employees_id_seq OWNER TO neondb_owner;

--
-- Name: branch_employees_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.branch_employees_id_seq OWNED BY public.branch_employees.id;


--
-- Name: branches; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.branches (
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


ALTER TABLE public.branches OWNER TO neondb_owner;

--
-- Name: branches_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.branches_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.branches_id_seq OWNER TO neondb_owner;

--
-- Name: branches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.branches_id_seq OWNED BY public.branches.id;


--
-- Name: chart_of_accounts; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.chart_of_accounts (
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


ALTER TABLE public.chart_of_accounts OWNER TO neondb_owner;

--
-- Name: chart_of_accounts_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.chart_of_accounts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.chart_of_accounts_id_seq OWNER TO neondb_owner;

--
-- Name: chart_of_accounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.chart_of_accounts_id_seq OWNED BY public.chart_of_accounts.id;


--
-- Name: company_settings; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.company_settings (
    id integer NOT NULL,
    setting_key character varying(100) NOT NULL,
    setting_value text,
    setting_type character varying(20) DEFAULT 'text'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.company_settings OWNER TO neondb_owner;

--
-- Name: company_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.company_settings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.company_settings_id_seq OWNER TO neondb_owner;

--
-- Name: company_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.company_settings_id_seq OWNED BY public.company_settings.id;


--
-- Name: complaints; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.complaints (
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


ALTER TABLE public.complaints OWNER TO neondb_owner;

--
-- Name: complaints_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.complaints_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.complaints_id_seq OWNER TO neondb_owner;

--
-- Name: complaints_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.complaints_id_seq OWNED BY public.complaints.id;


--
-- Name: customer_payments; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.customer_payments (
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


ALTER TABLE public.customer_payments OWNER TO neondb_owner;

--
-- Name: customer_payments_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.customer_payments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.customer_payments_id_seq OWNER TO neondb_owner;

--
-- Name: customer_payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.customer_payments_id_seq OWNED BY public.customer_payments.id;


--
-- Name: customer_ticket_tokens; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.customer_ticket_tokens (
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


ALTER TABLE public.customer_ticket_tokens OWNER TO neondb_owner;

--
-- Name: customer_ticket_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.customer_ticket_tokens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.customer_ticket_tokens_id_seq OWNER TO neondb_owner;

--
-- Name: customer_ticket_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.customer_ticket_tokens_id_seq OWNED BY public.customer_ticket_tokens.id;


--
-- Name: customers; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.customers (
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


ALTER TABLE public.customers OWNER TO neondb_owner;

--
-- Name: customers_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.customers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.customers_id_seq OWNER TO neondb_owner;

--
-- Name: customers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.customers_id_seq OWNED BY public.customers.id;


--
-- Name: departments; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.departments (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    manager_id integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.departments OWNER TO neondb_owner;

--
-- Name: departments_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.departments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.departments_id_seq OWNER TO neondb_owner;

--
-- Name: departments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.departments_id_seq OWNED BY public.departments.id;


--
-- Name: device_interfaces; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.device_interfaces (
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


ALTER TABLE public.device_interfaces OWNER TO neondb_owner;

--
-- Name: device_interfaces_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.device_interfaces_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.device_interfaces_id_seq OWNER TO neondb_owner;

--
-- Name: device_interfaces_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.device_interfaces_id_seq OWNED BY public.device_interfaces.id;


--
-- Name: device_monitoring_log; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.device_monitoring_log (
    id integer NOT NULL,
    device_id integer,
    metric_type character varying(50) NOT NULL,
    metric_name character varying(100),
    metric_value text,
    recorded_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.device_monitoring_log OWNER TO neondb_owner;

--
-- Name: device_monitoring_log_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.device_monitoring_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.device_monitoring_log_id_seq OWNER TO neondb_owner;

--
-- Name: device_monitoring_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.device_monitoring_log_id_seq OWNED BY public.device_monitoring_log.id;


--
-- Name: device_onus; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.device_onus (
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


ALTER TABLE public.device_onus OWNER TO neondb_owner;

--
-- Name: device_onus_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.device_onus_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.device_onus_id_seq OWNER TO neondb_owner;

--
-- Name: device_onus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.device_onus_id_seq OWNED BY public.device_onus.id;


--
-- Name: device_user_mapping; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.device_user_mapping (
    id integer NOT NULL,
    device_id integer,
    device_user_id character varying(50) NOT NULL,
    employee_id integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.device_user_mapping OWNER TO neondb_owner;

--
-- Name: device_user_mapping_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.device_user_mapping_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.device_user_mapping_id_seq OWNER TO neondb_owner;

--
-- Name: device_user_mapping_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.device_user_mapping_id_seq OWNED BY public.device_user_mapping.id;


--
-- Name: device_vlans; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.device_vlans (
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


ALTER TABLE public.device_vlans OWNER TO neondb_owner;

--
-- Name: device_vlans_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.device_vlans_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.device_vlans_id_seq OWNER TO neondb_owner;

--
-- Name: device_vlans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.device_vlans_id_seq OWNED BY public.device_vlans.id;


--
-- Name: employee_branches; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.employee_branches (
    id integer NOT NULL,
    employee_id integer NOT NULL,
    branch_id integer NOT NULL,
    is_primary boolean DEFAULT false,
    assigned_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    assigned_by integer
);


ALTER TABLE public.employee_branches OWNER TO neondb_owner;

--
-- Name: employee_branches_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.employee_branches_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.employee_branches_id_seq OWNER TO neondb_owner;

--
-- Name: employee_branches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.employee_branches_id_seq OWNED BY public.employee_branches.id;


--
-- Name: employees; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.employees (
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
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.employees OWNER TO neondb_owner;

--
-- Name: employees_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.employees_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.employees_id_seq OWNER TO neondb_owner;

--
-- Name: employees_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.employees_id_seq OWNED BY public.employees.id;


--
-- Name: equipment; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.equipment (
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


ALTER TABLE public.equipment OWNER TO neondb_owner;

--
-- Name: equipment_assignments; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.equipment_assignments (
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


ALTER TABLE public.equipment_assignments OWNER TO neondb_owner;

--
-- Name: equipment_assignments_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.equipment_assignments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.equipment_assignments_id_seq OWNER TO neondb_owner;

--
-- Name: equipment_assignments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.equipment_assignments_id_seq OWNED BY public.equipment_assignments.id;


--
-- Name: equipment_categories; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.equipment_categories (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    parent_id integer,
    item_type character varying(30) DEFAULT 'serialized'::character varying
);


ALTER TABLE public.equipment_categories OWNER TO neondb_owner;

--
-- Name: equipment_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.equipment_categories_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.equipment_categories_id_seq OWNER TO neondb_owner;

--
-- Name: equipment_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.equipment_categories_id_seq OWNED BY public.equipment_categories.id;


--
-- Name: equipment_faults; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.equipment_faults (
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


ALTER TABLE public.equipment_faults OWNER TO neondb_owner;

--
-- Name: equipment_faults_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.equipment_faults_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.equipment_faults_id_seq OWNER TO neondb_owner;

--
-- Name: equipment_faults_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.equipment_faults_id_seq OWNED BY public.equipment_faults.id;


--
-- Name: equipment_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.equipment_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.equipment_id_seq OWNER TO neondb_owner;

--
-- Name: equipment_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.equipment_id_seq OWNED BY public.equipment.id;


--
-- Name: equipment_lifecycle_logs; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.equipment_lifecycle_logs (
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


ALTER TABLE public.equipment_lifecycle_logs OWNER TO neondb_owner;

--
-- Name: equipment_lifecycle_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.equipment_lifecycle_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.equipment_lifecycle_logs_id_seq OWNER TO neondb_owner;

--
-- Name: equipment_lifecycle_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.equipment_lifecycle_logs_id_seq OWNED BY public.equipment_lifecycle_logs.id;


--
-- Name: equipment_loans; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.equipment_loans (
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


ALTER TABLE public.equipment_loans OWNER TO neondb_owner;

--
-- Name: equipment_loans_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.equipment_loans_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.equipment_loans_id_seq OWNER TO neondb_owner;

--
-- Name: equipment_loans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.equipment_loans_id_seq OWNED BY public.equipment_loans.id;


--
-- Name: expense_categories; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.expense_categories (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    account_id integer,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.expense_categories OWNER TO neondb_owner;

--
-- Name: expense_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.expense_categories_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.expense_categories_id_seq OWNER TO neondb_owner;

--
-- Name: expense_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.expense_categories_id_seq OWNED BY public.expense_categories.id;


--
-- Name: expenses; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.expenses (
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


ALTER TABLE public.expenses OWNER TO neondb_owner;

--
-- Name: expenses_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.expenses_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.expenses_id_seq OWNER TO neondb_owner;

--
-- Name: expenses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.expenses_id_seq OWNED BY public.expenses.id;


--
-- Name: hr_notification_templates; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.hr_notification_templates (
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


ALTER TABLE public.hr_notification_templates OWNER TO neondb_owner;

--
-- Name: hr_notification_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.hr_notification_templates_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.hr_notification_templates_id_seq OWNER TO neondb_owner;

--
-- Name: hr_notification_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.hr_notification_templates_id_seq OWNED BY public.hr_notification_templates.id;


--
-- Name: huawei_alerts; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_alerts (
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


ALTER TABLE public.huawei_alerts OWNER TO neondb_owner;

--
-- Name: huawei_alerts_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_alerts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_alerts_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_alerts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_alerts_id_seq OWNED BY public.huawei_alerts.id;


--
-- Name: huawei_apartments; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_apartments (
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


ALTER TABLE public.huawei_apartments OWNER TO neondb_owner;

--
-- Name: huawei_apartments_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_apartments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_apartments_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_apartments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_apartments_id_seq OWNED BY public.huawei_apartments.id;


--
-- Name: huawei_boards; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_boards (
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


ALTER TABLE public.huawei_boards OWNER TO neondb_owner;

--
-- Name: huawei_boards_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_boards_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_boards_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_boards_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_boards_id_seq OWNED BY public.huawei_boards.id;


--
-- Name: huawei_odb_units; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_odb_units (
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


ALTER TABLE public.huawei_odb_units OWNER TO neondb_owner;

--
-- Name: huawei_odb_units_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_odb_units_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_odb_units_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_odb_units_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_odb_units_id_seq OWNED BY public.huawei_odb_units.id;


--
-- Name: huawei_olt_boards; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_olt_boards (
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


ALTER TABLE public.huawei_olt_boards OWNER TO neondb_owner;

--
-- Name: huawei_olt_boards_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_olt_boards_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_olt_boards_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_olt_boards_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_olt_boards_id_seq OWNED BY public.huawei_olt_boards.id;


--
-- Name: huawei_olt_pon_ports; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_olt_pon_ports (
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
    max_onus integer DEFAULT 128
);


ALTER TABLE public.huawei_olt_pon_ports OWNER TO neondb_owner;

--
-- Name: huawei_olt_pon_ports_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_olt_pon_ports_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_olt_pon_ports_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_olt_pon_ports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_olt_pon_ports_id_seq OWNED BY public.huawei_olt_pon_ports.id;


--
-- Name: huawei_olt_uplinks; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_olt_uplinks (
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


ALTER TABLE public.huawei_olt_uplinks OWNER TO neondb_owner;

--
-- Name: huawei_olt_uplinks_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_olt_uplinks_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_olt_uplinks_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_olt_uplinks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_olt_uplinks_id_seq OWNED BY public.huawei_olt_uplinks.id;


--
-- Name: huawei_olt_vlans; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_olt_vlans (
    id integer NOT NULL,
    olt_id integer NOT NULL,
    vlan_id integer NOT NULL,
    vlan_type character varying(50) DEFAULT 'smart'::character varying,
    description character varying(255),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.huawei_olt_vlans OWNER TO neondb_owner;

--
-- Name: huawei_olt_vlans_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_olt_vlans_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_olt_vlans_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_olt_vlans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_olt_vlans_id_seq OWNED BY public.huawei_olt_vlans.id;


--
-- Name: huawei_olts; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_olts (
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
    smartolt_id integer
);


ALTER TABLE public.huawei_olts OWNER TO neondb_owner;

--
-- Name: huawei_olts_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_olts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_olts_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_olts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_olts_id_seq OWNED BY public.huawei_olts.id;


--
-- Name: huawei_onu_mgmt_ips; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_onu_mgmt_ips (
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


ALTER TABLE public.huawei_onu_mgmt_ips OWNER TO neondb_owner;

--
-- Name: huawei_onu_mgmt_ips_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_onu_mgmt_ips_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_onu_mgmt_ips_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_onu_mgmt_ips_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_onu_mgmt_ips_id_seq OWNED BY public.huawei_onu_mgmt_ips.id;


--
-- Name: huawei_onu_tr069_config; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_onu_tr069_config (
    onu_id integer NOT NULL,
    config_data text,
    status character varying(20) DEFAULT 'pending'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone,
    applied_at timestamp without time zone
);


ALTER TABLE public.huawei_onu_tr069_config OWNER TO neondb_owner;

--
-- Name: huawei_onu_types; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_onu_types (
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
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.huawei_onu_types OWNER TO neondb_owner;

--
-- Name: huawei_onu_types_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_onu_types_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_onu_types_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_onu_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_onu_types_id_seq OWNED BY public.huawei_onu_types.id;


--
-- Name: huawei_onus; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_onus (
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
    smartolt_external_id character varying(100)
);


ALTER TABLE public.huawei_onus OWNER TO neondb_owner;

--
-- Name: COLUMN huawei_onus.vlan_id; Type: COMMENT; Schema: public; Owner: neondb_owner
--

COMMENT ON COLUMN public.huawei_onus.vlan_id IS 'VLAN ID assigned to ONU (from ont ipconfig)';


--
-- Name: COLUMN huawei_onus.vlan_priority; Type: COMMENT; Schema: public; Owner: neondb_owner
--

COMMENT ON COLUMN public.huawei_onus.vlan_priority IS 'VLAN priority 0-7 (from ont ipconfig priority)';


--
-- Name: COLUMN huawei_onus.ip_mode; Type: COMMENT; Schema: public; Owner: neondb_owner
--

COMMENT ON COLUMN public.huawei_onus.ip_mode IS 'IP assignment mode: dhcp or static';


--
-- Name: COLUMN huawei_onus.line_profile_id; Type: COMMENT; Schema: public; Owner: neondb_owner
--

COMMENT ON COLUMN public.huawei_onus.line_profile_id IS 'Huawei ont-lineprofile-id number';


--
-- Name: COLUMN huawei_onus.srv_profile_id; Type: COMMENT; Schema: public; Owner: neondb_owner
--

COMMENT ON COLUMN public.huawei_onus.srv_profile_id IS 'Huawei ont-srvprofile-id number';


--
-- Name: COLUMN huawei_onus.tr069_profile_id; Type: COMMENT; Schema: public; Owner: neondb_owner
--

COMMENT ON COLUMN public.huawei_onus.tr069_profile_id IS 'TR-069 server profile ID';


--
-- Name: COLUMN huawei_onus.zone; Type: COMMENT; Schema: public; Owner: neondb_owner
--

COMMENT ON COLUMN public.huawei_onus.zone IS 'Zone/region from ONU description';


--
-- Name: COLUMN huawei_onus.area; Type: COMMENT; Schema: public; Owner: neondb_owner
--

COMMENT ON COLUMN public.huawei_onus.area IS 'Area/location within zone from description';


--
-- Name: COLUMN huawei_onus.customer_name; Type: COMMENT; Schema: public; Owner: neondb_owner
--

COMMENT ON COLUMN public.huawei_onus.customer_name IS 'Customer name from ONU description';


--
-- Name: COLUMN huawei_onus.auth_date; Type: COMMENT; Schema: public; Owner: neondb_owner
--

COMMENT ON COLUMN public.huawei_onus.auth_date IS 'Authorization date from ONU description';


--
-- Name: huawei_onus_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_onus_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_onus_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_onus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_onus_id_seq OWNED BY public.huawei_onus.id;


--
-- Name: huawei_pon_ports; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_pon_ports (
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
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.huawei_pon_ports OWNER TO neondb_owner;

--
-- Name: huawei_pon_ports_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_pon_ports_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_pon_ports_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_pon_ports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_pon_ports_id_seq OWNED BY public.huawei_pon_ports.id;


--
-- Name: huawei_port_vlans; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_port_vlans (
    id integer NOT NULL,
    olt_id integer NOT NULL,
    port_name character varying(20) NOT NULL,
    port_type character varying(10) DEFAULT 'pon'::character varying NOT NULL,
    vlan_id integer NOT NULL,
    vlan_mode character varying(20) DEFAULT 'tag'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.huawei_port_vlans OWNER TO neondb_owner;

--
-- Name: huawei_port_vlans_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_port_vlans_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_port_vlans_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_port_vlans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_port_vlans_id_seq OWNED BY public.huawei_port_vlans.id;


--
-- Name: huawei_provisioning_logs; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_provisioning_logs (
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


ALTER TABLE public.huawei_provisioning_logs OWNER TO neondb_owner;

--
-- Name: huawei_provisioning_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_provisioning_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_provisioning_logs_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_provisioning_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_provisioning_logs_id_seq OWNED BY public.huawei_provisioning_logs.id;


--
-- Name: huawei_service_profiles; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_service_profiles (
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


ALTER TABLE public.huawei_service_profiles OWNER TO neondb_owner;

--
-- Name: huawei_service_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_service_profiles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_service_profiles_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_service_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_service_profiles_id_seq OWNED BY public.huawei_service_profiles.id;


--
-- Name: huawei_service_templates; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_service_templates (
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


ALTER TABLE public.huawei_service_templates OWNER TO neondb_owner;

--
-- Name: huawei_service_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_service_templates_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_service_templates_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_service_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_service_templates_id_seq OWNED BY public.huawei_service_templates.id;


--
-- Name: huawei_subzones; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_subzones (
    id integer NOT NULL,
    zone_id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.huawei_subzones OWNER TO neondb_owner;

--
-- Name: huawei_subzones_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_subzones_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_subzones_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_subzones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_subzones_id_seq OWNED BY public.huawei_subzones.id;


--
-- Name: huawei_uplinks; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_uplinks (
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


ALTER TABLE public.huawei_uplinks OWNER TO neondb_owner;

--
-- Name: huawei_uplinks_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_uplinks_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_uplinks_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_uplinks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_uplinks_id_seq OWNED BY public.huawei_uplinks.id;


--
-- Name: huawei_vlans; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_vlans (
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


ALTER TABLE public.huawei_vlans OWNER TO neondb_owner;

--
-- Name: TABLE huawei_vlans; Type: COMMENT; Schema: public; Owner: neondb_owner
--

COMMENT ON TABLE public.huawei_vlans IS 'VLANs configured on Huawei OLT devices';


--
-- Name: COLUMN huawei_vlans.vlan_type; Type: COMMENT; Schema: public; Owner: neondb_owner
--

COMMENT ON COLUMN public.huawei_vlans.vlan_type IS 'VLAN type: smart, standard, mux, super';


--
-- Name: COLUMN huawei_vlans.attribute; Type: COMMENT; Schema: public; Owner: neondb_owner
--

COMMENT ON COLUMN public.huawei_vlans.attribute IS 'VLAN attribute: common, stacking, etc';


--
-- Name: COLUMN huawei_vlans.standard_port_count; Type: COMMENT; Schema: public; Owner: neondb_owner
--

COMMENT ON COLUMN public.huawei_vlans.standard_port_count IS 'Number of standard ports using this VLAN';


--
-- Name: COLUMN huawei_vlans.service_port_count; Type: COMMENT; Schema: public; Owner: neondb_owner
--

COMMENT ON COLUMN public.huawei_vlans.service_port_count IS 'Number of service virtual ports (ONUs) using this VLAN';


--
-- Name: COLUMN huawei_vlans.is_management; Type: COMMENT; Schema: public; Owner: neondb_owner
--

COMMENT ON COLUMN public.huawei_vlans.is_management IS 'True if this is a management VLAN';


--
-- Name: huawei_vlans_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_vlans_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_vlans_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_vlans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_vlans_id_seq OWNED BY public.huawei_vlans.id;


--
-- Name: huawei_zones; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.huawei_zones (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.huawei_zones OWNER TO neondb_owner;

--
-- Name: huawei_zones_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.huawei_zones_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.huawei_zones_id_seq OWNER TO neondb_owner;

--
-- Name: huawei_zones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.huawei_zones_id_seq OWNED BY public.huawei_zones.id;


--
-- Name: interface_history; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.interface_history (
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


ALTER TABLE public.interface_history OWNER TO neondb_owner;

--
-- Name: interface_history_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.interface_history_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.interface_history_id_seq OWNER TO neondb_owner;

--
-- Name: interface_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.interface_history_id_seq OWNED BY public.interface_history.id;


--
-- Name: inventory_audit_items; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_audit_items (
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


ALTER TABLE public.inventory_audit_items OWNER TO neondb_owner;

--
-- Name: inventory_audit_items_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_audit_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_audit_items_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_audit_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_audit_items_id_seq OWNED BY public.inventory_audit_items.id;


--
-- Name: inventory_audits; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_audits (
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


ALTER TABLE public.inventory_audits OWNER TO neondb_owner;

--
-- Name: inventory_audits_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_audits_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_audits_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_audits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_audits_id_seq OWNED BY public.inventory_audits.id;


--
-- Name: inventory_locations; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_locations (
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


ALTER TABLE public.inventory_locations OWNER TO neondb_owner;

--
-- Name: inventory_locations_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_locations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_locations_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_locations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_locations_id_seq OWNED BY public.inventory_locations.id;


--
-- Name: inventory_loss_reports; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_loss_reports (
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


ALTER TABLE public.inventory_loss_reports OWNER TO neondb_owner;

--
-- Name: inventory_loss_reports_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_loss_reports_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_loss_reports_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_loss_reports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_loss_reports_id_seq OWNED BY public.inventory_loss_reports.id;


--
-- Name: inventory_po_items; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_po_items (
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


ALTER TABLE public.inventory_po_items OWNER TO neondb_owner;

--
-- Name: inventory_po_items_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_po_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_po_items_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_po_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_po_items_id_seq OWNED BY public.inventory_po_items.id;


--
-- Name: inventory_purchase_orders; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_purchase_orders (
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


ALTER TABLE public.inventory_purchase_orders OWNER TO neondb_owner;

--
-- Name: inventory_purchase_orders_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_purchase_orders_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_purchase_orders_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_purchase_orders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_purchase_orders_id_seq OWNED BY public.inventory_purchase_orders.id;


--
-- Name: inventory_receipt_items; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_receipt_items (
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


ALTER TABLE public.inventory_receipt_items OWNER TO neondb_owner;

--
-- Name: inventory_receipt_items_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_receipt_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_receipt_items_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_receipt_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_receipt_items_id_seq OWNED BY public.inventory_receipt_items.id;


--
-- Name: inventory_receipts; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_receipts (
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


ALTER TABLE public.inventory_receipts OWNER TO neondb_owner;

--
-- Name: inventory_receipts_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_receipts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_receipts_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_receipts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_receipts_id_seq OWNED BY public.inventory_receipts.id;


--
-- Name: inventory_return_items; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_return_items (
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


ALTER TABLE public.inventory_return_items OWNER TO neondb_owner;

--
-- Name: inventory_return_items_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_return_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_return_items_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_return_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_return_items_id_seq OWNED BY public.inventory_return_items.id;


--
-- Name: inventory_returns; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_returns (
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


ALTER TABLE public.inventory_returns OWNER TO neondb_owner;

--
-- Name: inventory_returns_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_returns_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_returns_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_returns_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_returns_id_seq OWNED BY public.inventory_returns.id;


--
-- Name: inventory_rma; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_rma (
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


ALTER TABLE public.inventory_rma OWNER TO neondb_owner;

--
-- Name: inventory_rma_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_rma_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_rma_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_rma_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_rma_id_seq OWNED BY public.inventory_rma.id;


--
-- Name: inventory_stock_levels; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_stock_levels (
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


ALTER TABLE public.inventory_stock_levels OWNER TO neondb_owner;

--
-- Name: inventory_stock_levels_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_stock_levels_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_stock_levels_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_stock_levels_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_stock_levels_id_seq OWNED BY public.inventory_stock_levels.id;


--
-- Name: inventory_stock_movements; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_stock_movements (
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


ALTER TABLE public.inventory_stock_movements OWNER TO neondb_owner;

--
-- Name: inventory_stock_movements_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_stock_movements_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_stock_movements_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_stock_movements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_stock_movements_id_seq OWNED BY public.inventory_stock_movements.id;


--
-- Name: inventory_stock_request_items; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_stock_request_items (
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


ALTER TABLE public.inventory_stock_request_items OWNER TO neondb_owner;

--
-- Name: inventory_stock_request_items_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_stock_request_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_stock_request_items_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_stock_request_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_stock_request_items_id_seq OWNED BY public.inventory_stock_request_items.id;


--
-- Name: inventory_stock_requests; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_stock_requests (
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


ALTER TABLE public.inventory_stock_requests OWNER TO neondb_owner;

--
-- Name: inventory_stock_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_stock_requests_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_stock_requests_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_stock_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_stock_requests_id_seq OWNED BY public.inventory_stock_requests.id;


--
-- Name: inventory_thresholds; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_thresholds (
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


ALTER TABLE public.inventory_thresholds OWNER TO neondb_owner;

--
-- Name: inventory_thresholds_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_thresholds_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_thresholds_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_thresholds_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_thresholds_id_seq OWNED BY public.inventory_thresholds.id;


--
-- Name: inventory_usage; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_usage (
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


ALTER TABLE public.inventory_usage OWNER TO neondb_owner;

--
-- Name: inventory_usage_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_usage_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_usage_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_usage_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_usage_id_seq OWNED BY public.inventory_usage.id;


--
-- Name: inventory_warehouses; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.inventory_warehouses (
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


ALTER TABLE public.inventory_warehouses OWNER TO neondb_owner;

--
-- Name: inventory_warehouses_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.inventory_warehouses_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inventory_warehouses_id_seq OWNER TO neondb_owner;

--
-- Name: inventory_warehouses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.inventory_warehouses_id_seq OWNED BY public.inventory_warehouses.id;


--
-- Name: invoice_items; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.invoice_items (
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


ALTER TABLE public.invoice_items OWNER TO neondb_owner;

--
-- Name: invoice_items_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.invoice_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.invoice_items_id_seq OWNER TO neondb_owner;

--
-- Name: invoice_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.invoice_items_id_seq OWNED BY public.invoice_items.id;


--
-- Name: invoices; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.invoices (
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


ALTER TABLE public.invoices OWNER TO neondb_owner;

--
-- Name: invoices_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.invoices_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.invoices_id_seq OWNER TO neondb_owner;

--
-- Name: invoices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.invoices_id_seq OWNED BY public.invoices.id;


--
-- Name: late_rules; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.late_rules (
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


ALTER TABLE public.late_rules OWNER TO neondb_owner;

--
-- Name: late_rules_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.late_rules_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.late_rules_id_seq OWNER TO neondb_owner;

--
-- Name: late_rules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.late_rules_id_seq OWNED BY public.late_rules.id;


--
-- Name: leave_balances; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.leave_balances (
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


ALTER TABLE public.leave_balances OWNER TO neondb_owner;

--
-- Name: leave_balances_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.leave_balances_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.leave_balances_id_seq OWNER TO neondb_owner;

--
-- Name: leave_balances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.leave_balances_id_seq OWNED BY public.leave_balances.id;


--
-- Name: leave_calendar; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.leave_calendar (
    id integer NOT NULL,
    date date NOT NULL,
    name character varying(255) NOT NULL,
    is_public_holiday boolean DEFAULT false,
    branch_id integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.leave_calendar OWNER TO neondb_owner;

--
-- Name: leave_calendar_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.leave_calendar_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.leave_calendar_id_seq OWNER TO neondb_owner;

--
-- Name: leave_calendar_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.leave_calendar_id_seq OWNED BY public.leave_calendar.id;


--
-- Name: leave_requests; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.leave_requests (
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


ALTER TABLE public.leave_requests OWNER TO neondb_owner;

--
-- Name: leave_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.leave_requests_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.leave_requests_id_seq OWNER TO neondb_owner;

--
-- Name: leave_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.leave_requests_id_seq OWNED BY public.leave_requests.id;


--
-- Name: leave_types; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.leave_types (
    id integer NOT NULL,
    name character varying(50) NOT NULL,
    code character varying(20) NOT NULL,
    days_per_year integer DEFAULT 0,
    is_paid boolean DEFAULT true,
    requires_approval boolean DEFAULT true,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.leave_types OWNER TO neondb_owner;

--
-- Name: leave_types_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.leave_types_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.leave_types_id_seq OWNER TO neondb_owner;

--
-- Name: leave_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.leave_types_id_seq OWNED BY public.leave_types.id;


--
-- Name: mobile_notifications; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.mobile_notifications (
    id integer NOT NULL,
    user_id integer NOT NULL,
    type character varying(50) NOT NULL,
    title character varying(255) NOT NULL,
    message text NOT NULL,
    data jsonb DEFAULT '{}'::jsonb,
    is_read boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.mobile_notifications OWNER TO neondb_owner;

--
-- Name: mobile_notifications_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.mobile_notifications_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mobile_notifications_id_seq OWNER TO neondb_owner;

--
-- Name: mobile_notifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.mobile_notifications_id_seq OWNED BY public.mobile_notifications.id;


--
-- Name: mobile_tokens; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.mobile_tokens (
    id integer NOT NULL,
    user_id integer,
    token character varying(64) NOT NULL,
    expires_at timestamp without time zone NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.mobile_tokens OWNER TO neondb_owner;

--
-- Name: mobile_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.mobile_tokens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mobile_tokens_id_seq OWNER TO neondb_owner;

--
-- Name: mobile_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.mobile_tokens_id_seq OWNED BY public.mobile_tokens.id;


--
-- Name: mpesa_b2b_transactions; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.mpesa_b2b_transactions (
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


ALTER TABLE public.mpesa_b2b_transactions OWNER TO neondb_owner;

--
-- Name: mpesa_b2b_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.mpesa_b2b_transactions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mpesa_b2b_transactions_id_seq OWNER TO neondb_owner;

--
-- Name: mpesa_b2b_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.mpesa_b2b_transactions_id_seq OWNED BY public.mpesa_b2b_transactions.id;


--
-- Name: mpesa_b2c_transactions; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.mpesa_b2c_transactions (
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


ALTER TABLE public.mpesa_b2c_transactions OWNER TO neondb_owner;

--
-- Name: mpesa_b2c_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.mpesa_b2c_transactions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mpesa_b2c_transactions_id_seq OWNER TO neondb_owner;

--
-- Name: mpesa_b2c_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.mpesa_b2c_transactions_id_seq OWNED BY public.mpesa_b2c_transactions.id;


--
-- Name: mpesa_c2b_transactions; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.mpesa_c2b_transactions (
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


ALTER TABLE public.mpesa_c2b_transactions OWNER TO neondb_owner;

--
-- Name: mpesa_c2b_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.mpesa_c2b_transactions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mpesa_c2b_transactions_id_seq OWNER TO neondb_owner;

--
-- Name: mpesa_c2b_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.mpesa_c2b_transactions_id_seq OWNED BY public.mpesa_c2b_transactions.id;


--
-- Name: mpesa_config; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.mpesa_config (
    id integer NOT NULL,
    config_key character varying(50) NOT NULL,
    config_value text,
    is_encrypted boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.mpesa_config OWNER TO neondb_owner;

--
-- Name: mpesa_config_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.mpesa_config_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mpesa_config_id_seq OWNER TO neondb_owner;

--
-- Name: mpesa_config_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.mpesa_config_id_seq OWNED BY public.mpesa_config.id;


--
-- Name: mpesa_transactions; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.mpesa_transactions (
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


ALTER TABLE public.mpesa_transactions OWNER TO neondb_owner;

--
-- Name: mpesa_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.mpesa_transactions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mpesa_transactions_id_seq OWNER TO neondb_owner;

--
-- Name: mpesa_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.mpesa_transactions_id_seq OWNED BY public.mpesa_transactions.id;


--
-- Name: network_devices; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.network_devices (
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


ALTER TABLE public.network_devices OWNER TO neondb_owner;

--
-- Name: network_devices_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.network_devices_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.network_devices_id_seq OWNER TO neondb_owner;

--
-- Name: network_devices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.network_devices_id_seq OWNED BY public.network_devices.id;


--
-- Name: onu_discovery_log; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.onu_discovery_log (
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
    equipment_id character varying(100)
);


ALTER TABLE public.onu_discovery_log OWNER TO neondb_owner;

--
-- Name: onu_discovery_log_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.onu_discovery_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.onu_discovery_log_id_seq OWNER TO neondb_owner;

--
-- Name: onu_discovery_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.onu_discovery_log_id_seq OWNED BY public.onu_discovery_log.id;


--
-- Name: onu_signal_history; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.onu_signal_history (
    id integer NOT NULL,
    onu_id integer,
    rx_power numeric(6,2),
    tx_power numeric(6,2),
    status character varying(20),
    recorded_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.onu_signal_history OWNER TO neondb_owner;

--
-- Name: onu_signal_history_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.onu_signal_history_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.onu_signal_history_id_seq OWNER TO neondb_owner;

--
-- Name: onu_signal_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.onu_signal_history_id_seq OWNED BY public.onu_signal_history.id;


--
-- Name: onu_uptime_log; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.onu_uptime_log (
    id integer NOT NULL,
    onu_id integer,
    status character varying(20) NOT NULL,
    started_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    ended_at timestamp without time zone,
    duration_seconds integer
);


ALTER TABLE public.onu_uptime_log OWNER TO neondb_owner;

--
-- Name: onu_uptime_log_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.onu_uptime_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.onu_uptime_log_id_seq OWNER TO neondb_owner;

--
-- Name: onu_uptime_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.onu_uptime_log_id_seq OWNED BY public.onu_uptime_log.id;


--
-- Name: orders; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.orders (
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


ALTER TABLE public.orders OWNER TO neondb_owner;

--
-- Name: orders_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.orders_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.orders_id_seq OWNER TO neondb_owner;

--
-- Name: orders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.orders_id_seq OWNED BY public.orders.id;


--
-- Name: payroll; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.payroll (
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
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.payroll OWNER TO neondb_owner;

--
-- Name: payroll_commissions; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.payroll_commissions (
    id integer NOT NULL,
    payroll_id integer,
    employee_id integer,
    commission_type character varying(50) DEFAULT 'ticket'::character varying NOT NULL,
    description text,
    amount numeric(12,2) NOT NULL,
    details jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.payroll_commissions OWNER TO neondb_owner;

--
-- Name: payroll_commissions_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.payroll_commissions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.payroll_commissions_id_seq OWNER TO neondb_owner;

--
-- Name: payroll_commissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.payroll_commissions_id_seq OWNED BY public.payroll_commissions.id;


--
-- Name: payroll_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.payroll_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.payroll_id_seq OWNER TO neondb_owner;

--
-- Name: payroll_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.payroll_id_seq OWNED BY public.payroll.id;


--
-- Name: performance_reviews; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.performance_reviews (
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


ALTER TABLE public.performance_reviews OWNER TO neondb_owner;

--
-- Name: performance_reviews_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.performance_reviews_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.performance_reviews_id_seq OWNER TO neondb_owner;

--
-- Name: performance_reviews_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.performance_reviews_id_seq OWNED BY public.performance_reviews.id;


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.permissions (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    display_name character varying(150) NOT NULL,
    category character varying(50) NOT NULL,
    description text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.permissions OWNER TO neondb_owner;

--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.permissions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.permissions_id_seq OWNER TO neondb_owner;

--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: products_services; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.products_services (
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


ALTER TABLE public.products_services OWNER TO neondb_owner;

--
-- Name: products_services_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.products_services_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.products_services_id_seq OWNER TO neondb_owner;

--
-- Name: products_services_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.products_services_id_seq OWNED BY public.products_services.id;


--
-- Name: public_holidays; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.public_holidays (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    holiday_date date NOT NULL,
    is_recurring boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.public_holidays OWNER TO neondb_owner;

--
-- Name: public_holidays_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.public_holidays_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.public_holidays_id_seq OWNER TO neondb_owner;

--
-- Name: public_holidays_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.public_holidays_id_seq OWNED BY public.public_holidays.id;


--
-- Name: purchase_order_items; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.purchase_order_items (
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


ALTER TABLE public.purchase_order_items OWNER TO neondb_owner;

--
-- Name: purchase_order_items_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.purchase_order_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.purchase_order_items_id_seq OWNER TO neondb_owner;

--
-- Name: purchase_order_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.purchase_order_items_id_seq OWNED BY public.purchase_order_items.id;


--
-- Name: purchase_orders; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.purchase_orders (
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


ALTER TABLE public.purchase_orders OWNER TO neondb_owner;

--
-- Name: purchase_orders_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.purchase_orders_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.purchase_orders_id_seq OWNER TO neondb_owner;

--
-- Name: purchase_orders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.purchase_orders_id_seq OWNED BY public.purchase_orders.id;


--
-- Name: quote_items; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.quote_items (
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
    sort_order integer DEFAULT 0
);


ALTER TABLE public.quote_items OWNER TO neondb_owner;

--
-- Name: quote_items_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.quote_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.quote_items_id_seq OWNER TO neondb_owner;

--
-- Name: quote_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.quote_items_id_seq OWNED BY public.quote_items.id;


--
-- Name: quotes; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.quotes (
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


ALTER TABLE public.quotes OWNER TO neondb_owner;

--
-- Name: quotes_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.quotes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.quotes_id_seq OWNER TO neondb_owner;

--
-- Name: quotes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.quotes_id_seq OWNED BY public.quotes.id;


--
-- Name: role_permissions; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.role_permissions (
    id integer NOT NULL,
    role_id integer,
    permission_id integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.role_permissions OWNER TO neondb_owner;

--
-- Name: role_permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.role_permissions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.role_permissions_id_seq OWNER TO neondb_owner;

--
-- Name: role_permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.role_permissions_id_seq OWNED BY public.role_permissions.id;


--
-- Name: roles; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.roles (
    id integer NOT NULL,
    name character varying(50) NOT NULL,
    display_name character varying(100) NOT NULL,
    description text,
    is_system boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.roles OWNER TO neondb_owner;

--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.roles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.roles_id_seq OWNER TO neondb_owner;

--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: salary_advance_repayments; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.salary_advance_repayments (
    id integer NOT NULL,
    advance_id integer,
    amount numeric(12,2) NOT NULL,
    repayment_date date NOT NULL,
    payroll_id integer,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.salary_advance_repayments OWNER TO neondb_owner;

--
-- Name: salary_advance_repayments_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.salary_advance_repayments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.salary_advance_repayments_id_seq OWNER TO neondb_owner;

--
-- Name: salary_advance_repayments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.salary_advance_repayments_id_seq OWNED BY public.salary_advance_repayments.id;


--
-- Name: salary_advances; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.salary_advances (
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


ALTER TABLE public.salary_advances OWNER TO neondb_owner;

--
-- Name: salary_advances_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.salary_advances_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.salary_advances_id_seq OWNER TO neondb_owner;

--
-- Name: salary_advances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.salary_advances_id_seq OWNED BY public.salary_advances.id;


--
-- Name: sales_commissions; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.sales_commissions (
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


ALTER TABLE public.sales_commissions OWNER TO neondb_owner;

--
-- Name: sales_commissions_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.sales_commissions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sales_commissions_id_seq OWNER TO neondb_owner;

--
-- Name: sales_commissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.sales_commissions_id_seq OWNED BY public.sales_commissions.id;


--
-- Name: salespersons; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.salespersons (
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


ALTER TABLE public.salespersons OWNER TO neondb_owner;

--
-- Name: salespersons_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.salespersons_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.salespersons_id_seq OWNER TO neondb_owner;

--
-- Name: salespersons_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.salespersons_id_seq OWNED BY public.salespersons.id;


--
-- Name: schema_migrations; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.schema_migrations (
    id integer NOT NULL,
    version character varying(50) NOT NULL,
    applied_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.schema_migrations OWNER TO neondb_owner;

--
-- Name: schema_migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.schema_migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.schema_migrations_id_seq OWNER TO neondb_owner;

--
-- Name: schema_migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.schema_migrations_id_seq OWNED BY public.schema_migrations.id;


--
-- Name: service_fee_types; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.service_fee_types (
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


ALTER TABLE public.service_fee_types OWNER TO neondb_owner;

--
-- Name: service_fee_types_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.service_fee_types_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.service_fee_types_id_seq OWNER TO neondb_owner;

--
-- Name: service_fee_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.service_fee_types_id_seq OWNED BY public.service_fee_types.id;


--
-- Name: service_packages; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.service_packages (
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


ALTER TABLE public.service_packages OWNER TO neondb_owner;

--
-- Name: service_packages_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.service_packages_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.service_packages_id_seq OWNER TO neondb_owner;

--
-- Name: service_packages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.service_packages_id_seq OWNED BY public.service_packages.id;


--
-- Name: settings; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.settings (
    id integer NOT NULL,
    setting_key character varying(100) NOT NULL,
    setting_value text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.settings OWNER TO neondb_owner;

--
-- Name: settings_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.settings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.settings_id_seq OWNER TO neondb_owner;

--
-- Name: settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.settings_id_seq OWNED BY public.settings.id;


--
-- Name: sla_business_hours; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.sla_business_hours (
    id integer NOT NULL,
    day_of_week integer NOT NULL,
    start_time time without time zone DEFAULT '08:00:00'::time without time zone NOT NULL,
    end_time time without time zone DEFAULT '17:00:00'::time without time zone NOT NULL,
    is_working_day boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT sla_business_hours_day_of_week_check CHECK (((day_of_week >= 0) AND (day_of_week <= 6)))
);


ALTER TABLE public.sla_business_hours OWNER TO neondb_owner;

--
-- Name: sla_business_hours_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.sla_business_hours_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sla_business_hours_id_seq OWNER TO neondb_owner;

--
-- Name: sla_business_hours_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.sla_business_hours_id_seq OWNED BY public.sla_business_hours.id;


--
-- Name: sla_holidays; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.sla_holidays (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    holiday_date date NOT NULL,
    is_recurring boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.sla_holidays OWNER TO neondb_owner;

--
-- Name: sla_holidays_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.sla_holidays_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sla_holidays_id_seq OWNER TO neondb_owner;

--
-- Name: sla_holidays_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.sla_holidays_id_seq OWNED BY public.sla_holidays.id;


--
-- Name: sla_policies; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.sla_policies (
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


ALTER TABLE public.sla_policies OWNER TO neondb_owner;

--
-- Name: sla_policies_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.sla_policies_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sla_policies_id_seq OWNER TO neondb_owner;

--
-- Name: sla_policies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.sla_policies_id_seq OWNED BY public.sla_policies.id;


--
-- Name: sms_logs; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.sms_logs (
    id integer NOT NULL,
    ticket_id integer,
    recipient_phone character varying(20) NOT NULL,
    recipient_type character varying(20) NOT NULL,
    message text NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying,
    sent_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.sms_logs OWNER TO neondb_owner;

--
-- Name: sms_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.sms_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sms_logs_id_seq OWNER TO neondb_owner;

--
-- Name: sms_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.sms_logs_id_seq OWNED BY public.sms_logs.id;


--
-- Name: tax_rates; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.tax_rates (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    rate numeric(5,2) DEFAULT 16.00 NOT NULL,
    type character varying(20) DEFAULT 'percentage'::character varying,
    is_inclusive boolean DEFAULT false,
    is_default boolean DEFAULT false,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.tax_rates OWNER TO neondb_owner;

--
-- Name: tax_rates_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.tax_rates_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tax_rates_id_seq OWNER TO neondb_owner;

--
-- Name: tax_rates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.tax_rates_id_seq OWNED BY public.tax_rates.id;


--
-- Name: team_members; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.team_members (
    id integer NOT NULL,
    team_id integer NOT NULL,
    employee_id integer NOT NULL,
    joined_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.team_members OWNER TO neondb_owner;

--
-- Name: team_members_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.team_members_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.team_members_id_seq OWNER TO neondb_owner;

--
-- Name: team_members_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.team_members_id_seq OWNED BY public.team_members.id;


--
-- Name: teams; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.teams (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    description text,
    leader_id integer,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    branch_id integer
);


ALTER TABLE public.teams OWNER TO neondb_owner;

--
-- Name: teams_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.teams_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.teams_id_seq OWNER TO neondb_owner;

--
-- Name: teams_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.teams_id_seq OWNED BY public.teams.id;


--
-- Name: technician_kit_items; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.technician_kit_items (
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


ALTER TABLE public.technician_kit_items OWNER TO neondb_owner;

--
-- Name: technician_kit_items_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.technician_kit_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.technician_kit_items_id_seq OWNER TO neondb_owner;

--
-- Name: technician_kit_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.technician_kit_items_id_seq OWNED BY public.technician_kit_items.id;


--
-- Name: technician_kits; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.technician_kits (
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


ALTER TABLE public.technician_kits OWNER TO neondb_owner;

--
-- Name: technician_kits_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.technician_kits_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.technician_kits_id_seq OWNER TO neondb_owner;

--
-- Name: technician_kits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.technician_kits_id_seq OWNED BY public.technician_kits.id;


--
-- Name: ticket_categories; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.ticket_categories (
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


ALTER TABLE public.ticket_categories OWNER TO neondb_owner;

--
-- Name: ticket_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.ticket_categories_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ticket_categories_id_seq OWNER TO neondb_owner;

--
-- Name: ticket_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.ticket_categories_id_seq OWNED BY public.ticket_categories.id;


--
-- Name: ticket_comments; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.ticket_comments (
    id integer NOT NULL,
    ticket_id integer,
    user_id integer,
    comment text NOT NULL,
    is_internal boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.ticket_comments OWNER TO neondb_owner;

--
-- Name: ticket_comments_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.ticket_comments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ticket_comments_id_seq OWNER TO neondb_owner;

--
-- Name: ticket_comments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.ticket_comments_id_seq OWNED BY public.ticket_comments.id;


--
-- Name: ticket_commission_rates; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.ticket_commission_rates (
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


ALTER TABLE public.ticket_commission_rates OWNER TO neondb_owner;

--
-- Name: ticket_commission_rates_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.ticket_commission_rates_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ticket_commission_rates_id_seq OWNER TO neondb_owner;

--
-- Name: ticket_commission_rates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.ticket_commission_rates_id_seq OWNED BY public.ticket_commission_rates.id;


--
-- Name: ticket_earnings; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.ticket_earnings (
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


ALTER TABLE public.ticket_earnings OWNER TO neondb_owner;

--
-- Name: ticket_earnings_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.ticket_earnings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ticket_earnings_id_seq OWNER TO neondb_owner;

--
-- Name: ticket_earnings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.ticket_earnings_id_seq OWNED BY public.ticket_earnings.id;


--
-- Name: ticket_escalations; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.ticket_escalations (
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


ALTER TABLE public.ticket_escalations OWNER TO neondb_owner;

--
-- Name: ticket_escalations_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.ticket_escalations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ticket_escalations_id_seq OWNER TO neondb_owner;

--
-- Name: ticket_escalations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.ticket_escalations_id_seq OWNED BY public.ticket_escalations.id;


--
-- Name: ticket_satisfaction_ratings; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.ticket_satisfaction_ratings (
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


ALTER TABLE public.ticket_satisfaction_ratings OWNER TO neondb_owner;

--
-- Name: ticket_satisfaction_ratings_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.ticket_satisfaction_ratings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ticket_satisfaction_ratings_id_seq OWNER TO neondb_owner;

--
-- Name: ticket_satisfaction_ratings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.ticket_satisfaction_ratings_id_seq OWNED BY public.ticket_satisfaction_ratings.id;


--
-- Name: ticket_service_fees; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.ticket_service_fees (
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


ALTER TABLE public.ticket_service_fees OWNER TO neondb_owner;

--
-- Name: ticket_service_fees_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.ticket_service_fees_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ticket_service_fees_id_seq OWNER TO neondb_owner;

--
-- Name: ticket_service_fees_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.ticket_service_fees_id_seq OWNED BY public.ticket_service_fees.id;


--
-- Name: ticket_sla_logs; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.ticket_sla_logs (
    id integer NOT NULL,
    ticket_id integer,
    event_type character varying(50) NOT NULL,
    details text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.ticket_sla_logs OWNER TO neondb_owner;

--
-- Name: ticket_sla_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.ticket_sla_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ticket_sla_logs_id_seq OWNER TO neondb_owner;

--
-- Name: ticket_sla_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.ticket_sla_logs_id_seq OWNED BY public.ticket_sla_logs.id;


--
-- Name: ticket_status_tokens; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.ticket_status_tokens (
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


ALTER TABLE public.ticket_status_tokens OWNER TO neondb_owner;

--
-- Name: ticket_status_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.ticket_status_tokens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ticket_status_tokens_id_seq OWNER TO neondb_owner;

--
-- Name: ticket_status_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.ticket_status_tokens_id_seq OWNED BY public.ticket_status_tokens.id;


--
-- Name: ticket_templates; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.ticket_templates (
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


ALTER TABLE public.ticket_templates OWNER TO neondb_owner;

--
-- Name: ticket_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.ticket_templates_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ticket_templates_id_seq OWNER TO neondb_owner;

--
-- Name: ticket_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.ticket_templates_id_seq OWNED BY public.ticket_templates.id;


--
-- Name: tickets; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.tickets (
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


ALTER TABLE public.tickets OWNER TO neondb_owner;

--
-- Name: tickets_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.tickets_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tickets_id_seq OWNER TO neondb_owner;

--
-- Name: tickets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.tickets_id_seq OWNED BY public.tickets.id;


--
-- Name: tr069_devices; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.tr069_devices (
    id integer NOT NULL,
    onu_id integer,
    device_id character varying(255),
    serial_number character varying(64),
    manufacturer character varying(64),
    model character varying(64),
    last_inform timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone,
    ip_address character varying(45)
);


ALTER TABLE public.tr069_devices OWNER TO neondb_owner;

--
-- Name: tr069_devices_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.tr069_devices_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tr069_devices_id_seq OWNER TO neondb_owner;

--
-- Name: tr069_devices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.tr069_devices_id_seq OWNED BY public.tr069_devices.id;


--
-- Name: user_notifications; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.user_notifications (
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


ALTER TABLE public.user_notifications OWNER TO neondb_owner;

--
-- Name: user_notifications_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.user_notifications_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.user_notifications_id_seq OWNER TO neondb_owner;

--
-- Name: user_notifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.user_notifications_id_seq OWNED BY public.user_notifications.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.users (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    email character varying(100) NOT NULL,
    phone character varying(20) NOT NULL,
    role character varying(20) DEFAULT 'technician'::character varying NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    password_hash character varying(255),
    role_id integer
);


ALTER TABLE public.users OWNER TO neondb_owner;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.users_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO neondb_owner;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: vendor_bill_items; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.vendor_bill_items (
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


ALTER TABLE public.vendor_bill_items OWNER TO neondb_owner;

--
-- Name: vendor_bill_items_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.vendor_bill_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.vendor_bill_items_id_seq OWNER TO neondb_owner;

--
-- Name: vendor_bill_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.vendor_bill_items_id_seq OWNED BY public.vendor_bill_items.id;


--
-- Name: vendor_bills; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.vendor_bills (
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


ALTER TABLE public.vendor_bills OWNER TO neondb_owner;

--
-- Name: vendor_bills_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.vendor_bills_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.vendor_bills_id_seq OWNER TO neondb_owner;

--
-- Name: vendor_bills_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.vendor_bills_id_seq OWNED BY public.vendor_bills.id;


--
-- Name: vendor_payments; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.vendor_payments (
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


ALTER TABLE public.vendor_payments OWNER TO neondb_owner;

--
-- Name: vendor_payments_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.vendor_payments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.vendor_payments_id_seq OWNER TO neondb_owner;

--
-- Name: vendor_payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.vendor_payments_id_seq OWNED BY public.vendor_payments.id;


--
-- Name: vendors; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.vendors (
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


ALTER TABLE public.vendors OWNER TO neondb_owner;

--
-- Name: vendors_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.vendors_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.vendors_id_seq OWNER TO neondb_owner;

--
-- Name: vendors_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.vendors_id_seq OWNED BY public.vendors.id;


--
-- Name: vlan_history; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.vlan_history (
    id integer NOT NULL,
    vlan_record_id integer,
    in_octets bigint DEFAULT 0,
    out_octets bigint DEFAULT 0,
    in_rate bigint DEFAULT 0,
    out_rate bigint DEFAULT 0,
    recorded_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.vlan_history OWNER TO neondb_owner;

--
-- Name: vlan_history_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.vlan_history_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.vlan_history_id_seq OWNER TO neondb_owner;

--
-- Name: vlan_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.vlan_history_id_seq OWNED BY public.vlan_history.id;


--
-- Name: whatsapp_conversations; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.whatsapp_conversations (
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


ALTER TABLE public.whatsapp_conversations OWNER TO neondb_owner;

--
-- Name: whatsapp_conversations_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.whatsapp_conversations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.whatsapp_conversations_id_seq OWNER TO neondb_owner;

--
-- Name: whatsapp_conversations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.whatsapp_conversations_id_seq OWNED BY public.whatsapp_conversations.id;


--
-- Name: whatsapp_logs; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.whatsapp_logs (
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


ALTER TABLE public.whatsapp_logs OWNER TO neondb_owner;

--
-- Name: whatsapp_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.whatsapp_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.whatsapp_logs_id_seq OWNER TO neondb_owner;

--
-- Name: whatsapp_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.whatsapp_logs_id_seq OWNED BY public.whatsapp_logs.id;


--
-- Name: whatsapp_messages; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.whatsapp_messages (
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


ALTER TABLE public.whatsapp_messages OWNER TO neondb_owner;

--
-- Name: whatsapp_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.whatsapp_messages_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.whatsapp_messages_id_seq OWNER TO neondb_owner;

--
-- Name: whatsapp_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.whatsapp_messages_id_seq OWNED BY public.whatsapp_messages.id;


--
-- Name: wireguard_peers; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.wireguard_peers (
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


ALTER TABLE public.wireguard_peers OWNER TO neondb_owner;

--
-- Name: wireguard_peers_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.wireguard_peers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.wireguard_peers_id_seq OWNER TO neondb_owner;

--
-- Name: wireguard_peers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.wireguard_peers_id_seq OWNED BY public.wireguard_peers.id;


--
-- Name: wireguard_servers; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.wireguard_servers (
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


ALTER TABLE public.wireguard_servers OWNER TO neondb_owner;

--
-- Name: wireguard_servers_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.wireguard_servers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.wireguard_servers_id_seq OWNER TO neondb_owner;

--
-- Name: wireguard_servers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.wireguard_servers_id_seq OWNED BY public.wireguard_servers.id;


--
-- Name: wireguard_settings; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.wireguard_settings (
    id integer NOT NULL,
    setting_key character varying(100) NOT NULL,
    setting_value text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.wireguard_settings OWNER TO neondb_owner;

--
-- Name: wireguard_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.wireguard_settings_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.wireguard_settings_id_seq OWNER TO neondb_owner;

--
-- Name: wireguard_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.wireguard_settings_id_seq OWNED BY public.wireguard_settings.id;


--
-- Name: wireguard_subnets; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.wireguard_subnets (
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


ALTER TABLE public.wireguard_subnets OWNER TO neondb_owner;

--
-- Name: wireguard_subnets_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.wireguard_subnets_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.wireguard_subnets_id_seq OWNER TO neondb_owner;

--
-- Name: wireguard_subnets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.wireguard_subnets_id_seq OWNED BY public.wireguard_subnets.id;


--
-- Name: wireguard_sync_logs; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.wireguard_sync_logs (
    id integer NOT NULL,
    server_id integer,
    success boolean DEFAULT false,
    message text,
    synced_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.wireguard_sync_logs OWNER TO neondb_owner;

--
-- Name: wireguard_sync_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.wireguard_sync_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.wireguard_sync_logs_id_seq OWNER TO neondb_owner;

--
-- Name: wireguard_sync_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.wireguard_sync_logs_id_seq OWNED BY public.wireguard_sync_logs.id;


--
-- Name: accounting_settings id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.accounting_settings ALTER COLUMN id SET DEFAULT nextval('public.accounting_settings_id_seq'::regclass);


--
-- Name: activity_logs id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.activity_logs ALTER COLUMN id SET DEFAULT nextval('public.activity_logs_id_seq'::regclass);


--
-- Name: announcement_recipients id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.announcement_recipients ALTER COLUMN id SET DEFAULT nextval('public.announcement_recipients_id_seq'::regclass);


--
-- Name: announcements id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.announcements ALTER COLUMN id SET DEFAULT nextval('public.announcements_id_seq'::regclass);


--
-- Name: attendance id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.attendance ALTER COLUMN id SET DEFAULT nextval('public.attendance_id_seq'::regclass);


--
-- Name: attendance_notification_logs id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.attendance_notification_logs ALTER COLUMN id SET DEFAULT nextval('public.attendance_notification_logs_id_seq'::regclass);


--
-- Name: bill_reminders id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.bill_reminders ALTER COLUMN id SET DEFAULT nextval('public.bill_reminders_id_seq'::regclass);


--
-- Name: biometric_attendance_logs id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.biometric_attendance_logs ALTER COLUMN id SET DEFAULT nextval('public.biometric_attendance_logs_id_seq'::regclass);


--
-- Name: biometric_devices id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.biometric_devices ALTER COLUMN id SET DEFAULT nextval('public.biometric_devices_id_seq'::regclass);


--
-- Name: branch_employees id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.branch_employees ALTER COLUMN id SET DEFAULT nextval('public.branch_employees_id_seq'::regclass);


--
-- Name: branches id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.branches ALTER COLUMN id SET DEFAULT nextval('public.branches_id_seq'::regclass);


--
-- Name: chart_of_accounts id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.chart_of_accounts ALTER COLUMN id SET DEFAULT nextval('public.chart_of_accounts_id_seq'::regclass);


--
-- Name: company_settings id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.company_settings ALTER COLUMN id SET DEFAULT nextval('public.company_settings_id_seq'::regclass);


--
-- Name: complaints id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.complaints ALTER COLUMN id SET DEFAULT nextval('public.complaints_id_seq'::regclass);


--
-- Name: customer_payments id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.customer_payments ALTER COLUMN id SET DEFAULT nextval('public.customer_payments_id_seq'::regclass);


--
-- Name: customer_ticket_tokens id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.customer_ticket_tokens ALTER COLUMN id SET DEFAULT nextval('public.customer_ticket_tokens_id_seq'::regclass);


--
-- Name: customers id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.customers ALTER COLUMN id SET DEFAULT nextval('public.customers_id_seq'::regclass);


--
-- Name: departments id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.departments ALTER COLUMN id SET DEFAULT nextval('public.departments_id_seq'::regclass);


--
-- Name: device_interfaces id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_interfaces ALTER COLUMN id SET DEFAULT nextval('public.device_interfaces_id_seq'::regclass);


--
-- Name: device_monitoring_log id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_monitoring_log ALTER COLUMN id SET DEFAULT nextval('public.device_monitoring_log_id_seq'::regclass);


--
-- Name: device_onus id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_onus ALTER COLUMN id SET DEFAULT nextval('public.device_onus_id_seq'::regclass);


--
-- Name: device_user_mapping id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_user_mapping ALTER COLUMN id SET DEFAULT nextval('public.device_user_mapping_id_seq'::regclass);


--
-- Name: device_vlans id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_vlans ALTER COLUMN id SET DEFAULT nextval('public.device_vlans_id_seq'::regclass);


--
-- Name: employee_branches id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.employee_branches ALTER COLUMN id SET DEFAULT nextval('public.employee_branches_id_seq'::regclass);


--
-- Name: employees id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.employees ALTER COLUMN id SET DEFAULT nextval('public.employees_id_seq'::regclass);


--
-- Name: equipment id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment ALTER COLUMN id SET DEFAULT nextval('public.equipment_id_seq'::regclass);


--
-- Name: equipment_assignments id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_assignments ALTER COLUMN id SET DEFAULT nextval('public.equipment_assignments_id_seq'::regclass);


--
-- Name: equipment_categories id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_categories ALTER COLUMN id SET DEFAULT nextval('public.equipment_categories_id_seq'::regclass);


--
-- Name: equipment_faults id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_faults ALTER COLUMN id SET DEFAULT nextval('public.equipment_faults_id_seq'::regclass);


--
-- Name: equipment_lifecycle_logs id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_lifecycle_logs ALTER COLUMN id SET DEFAULT nextval('public.equipment_lifecycle_logs_id_seq'::regclass);


--
-- Name: equipment_loans id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_loans ALTER COLUMN id SET DEFAULT nextval('public.equipment_loans_id_seq'::regclass);


--
-- Name: expense_categories id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.expense_categories ALTER COLUMN id SET DEFAULT nextval('public.expense_categories_id_seq'::regclass);


--
-- Name: expenses id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.expenses ALTER COLUMN id SET DEFAULT nextval('public.expenses_id_seq'::regclass);


--
-- Name: hr_notification_templates id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.hr_notification_templates ALTER COLUMN id SET DEFAULT nextval('public.hr_notification_templates_id_seq'::regclass);


--
-- Name: huawei_alerts id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_alerts ALTER COLUMN id SET DEFAULT nextval('public.huawei_alerts_id_seq'::regclass);


--
-- Name: huawei_apartments id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_apartments ALTER COLUMN id SET DEFAULT nextval('public.huawei_apartments_id_seq'::regclass);


--
-- Name: huawei_boards id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_boards ALTER COLUMN id SET DEFAULT nextval('public.huawei_boards_id_seq'::regclass);


--
-- Name: huawei_odb_units id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_odb_units ALTER COLUMN id SET DEFAULT nextval('public.huawei_odb_units_id_seq'::regclass);


--
-- Name: huawei_olt_boards id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olt_boards ALTER COLUMN id SET DEFAULT nextval('public.huawei_olt_boards_id_seq'::regclass);


--
-- Name: huawei_olt_pon_ports id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olt_pon_ports ALTER COLUMN id SET DEFAULT nextval('public.huawei_olt_pon_ports_id_seq'::regclass);


--
-- Name: huawei_olt_uplinks id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olt_uplinks ALTER COLUMN id SET DEFAULT nextval('public.huawei_olt_uplinks_id_seq'::regclass);


--
-- Name: huawei_olt_vlans id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olt_vlans ALTER COLUMN id SET DEFAULT nextval('public.huawei_olt_vlans_id_seq'::regclass);


--
-- Name: huawei_olts id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olts ALTER COLUMN id SET DEFAULT nextval('public.huawei_olts_id_seq'::regclass);


--
-- Name: huawei_onu_mgmt_ips id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_onu_mgmt_ips ALTER COLUMN id SET DEFAULT nextval('public.huawei_onu_mgmt_ips_id_seq'::regclass);


--
-- Name: huawei_onu_types id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_onu_types ALTER COLUMN id SET DEFAULT nextval('public.huawei_onu_types_id_seq'::regclass);


--
-- Name: huawei_onus id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_onus ALTER COLUMN id SET DEFAULT nextval('public.huawei_onus_id_seq'::regclass);


--
-- Name: huawei_pon_ports id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_pon_ports ALTER COLUMN id SET DEFAULT nextval('public.huawei_pon_ports_id_seq'::regclass);


--
-- Name: huawei_port_vlans id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_port_vlans ALTER COLUMN id SET DEFAULT nextval('public.huawei_port_vlans_id_seq'::regclass);


--
-- Name: huawei_provisioning_logs id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_provisioning_logs ALTER COLUMN id SET DEFAULT nextval('public.huawei_provisioning_logs_id_seq'::regclass);


--
-- Name: huawei_service_profiles id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_service_profiles ALTER COLUMN id SET DEFAULT nextval('public.huawei_service_profiles_id_seq'::regclass);


--
-- Name: huawei_service_templates id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_service_templates ALTER COLUMN id SET DEFAULT nextval('public.huawei_service_templates_id_seq'::regclass);


--
-- Name: huawei_subzones id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_subzones ALTER COLUMN id SET DEFAULT nextval('public.huawei_subzones_id_seq'::regclass);


--
-- Name: huawei_uplinks id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_uplinks ALTER COLUMN id SET DEFAULT nextval('public.huawei_uplinks_id_seq'::regclass);


--
-- Name: huawei_vlans id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_vlans ALTER COLUMN id SET DEFAULT nextval('public.huawei_vlans_id_seq'::regclass);


--
-- Name: huawei_zones id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_zones ALTER COLUMN id SET DEFAULT nextval('public.huawei_zones_id_seq'::regclass);


--
-- Name: interface_history id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.interface_history ALTER COLUMN id SET DEFAULT nextval('public.interface_history_id_seq'::regclass);


--
-- Name: inventory_audit_items id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_audit_items ALTER COLUMN id SET DEFAULT nextval('public.inventory_audit_items_id_seq'::regclass);


--
-- Name: inventory_audits id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_audits ALTER COLUMN id SET DEFAULT nextval('public.inventory_audits_id_seq'::regclass);


--
-- Name: inventory_locations id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_locations ALTER COLUMN id SET DEFAULT nextval('public.inventory_locations_id_seq'::regclass);


--
-- Name: inventory_loss_reports id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_loss_reports ALTER COLUMN id SET DEFAULT nextval('public.inventory_loss_reports_id_seq'::regclass);


--
-- Name: inventory_po_items id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_po_items ALTER COLUMN id SET DEFAULT nextval('public.inventory_po_items_id_seq'::regclass);


--
-- Name: inventory_purchase_orders id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_purchase_orders ALTER COLUMN id SET DEFAULT nextval('public.inventory_purchase_orders_id_seq'::regclass);


--
-- Name: inventory_receipt_items id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_receipt_items ALTER COLUMN id SET DEFAULT nextval('public.inventory_receipt_items_id_seq'::regclass);


--
-- Name: inventory_receipts id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_receipts ALTER COLUMN id SET DEFAULT nextval('public.inventory_receipts_id_seq'::regclass);


--
-- Name: inventory_return_items id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_return_items ALTER COLUMN id SET DEFAULT nextval('public.inventory_return_items_id_seq'::regclass);


--
-- Name: inventory_returns id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_returns ALTER COLUMN id SET DEFAULT nextval('public.inventory_returns_id_seq'::regclass);


--
-- Name: inventory_rma id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_rma ALTER COLUMN id SET DEFAULT nextval('public.inventory_rma_id_seq'::regclass);


--
-- Name: inventory_stock_levels id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_levels ALTER COLUMN id SET DEFAULT nextval('public.inventory_stock_levels_id_seq'::regclass);


--
-- Name: inventory_stock_movements id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_movements ALTER COLUMN id SET DEFAULT nextval('public.inventory_stock_movements_id_seq'::regclass);


--
-- Name: inventory_stock_request_items id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_request_items ALTER COLUMN id SET DEFAULT nextval('public.inventory_stock_request_items_id_seq'::regclass);


--
-- Name: inventory_stock_requests id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_requests ALTER COLUMN id SET DEFAULT nextval('public.inventory_stock_requests_id_seq'::regclass);


--
-- Name: inventory_thresholds id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_thresholds ALTER COLUMN id SET DEFAULT nextval('public.inventory_thresholds_id_seq'::regclass);


--
-- Name: inventory_usage id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_usage ALTER COLUMN id SET DEFAULT nextval('public.inventory_usage_id_seq'::regclass);


--
-- Name: inventory_warehouses id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_warehouses ALTER COLUMN id SET DEFAULT nextval('public.inventory_warehouses_id_seq'::regclass);


--
-- Name: invoice_items id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.invoice_items ALTER COLUMN id SET DEFAULT nextval('public.invoice_items_id_seq'::regclass);


--
-- Name: invoices id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.invoices ALTER COLUMN id SET DEFAULT nextval('public.invoices_id_seq'::regclass);


--
-- Name: late_rules id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.late_rules ALTER COLUMN id SET DEFAULT nextval('public.late_rules_id_seq'::regclass);


--
-- Name: leave_balances id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.leave_balances ALTER COLUMN id SET DEFAULT nextval('public.leave_balances_id_seq'::regclass);


--
-- Name: leave_calendar id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.leave_calendar ALTER COLUMN id SET DEFAULT nextval('public.leave_calendar_id_seq'::regclass);


--
-- Name: leave_requests id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.leave_requests ALTER COLUMN id SET DEFAULT nextval('public.leave_requests_id_seq'::regclass);


--
-- Name: leave_types id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.leave_types ALTER COLUMN id SET DEFAULT nextval('public.leave_types_id_seq'::regclass);


--
-- Name: mobile_notifications id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mobile_notifications ALTER COLUMN id SET DEFAULT nextval('public.mobile_notifications_id_seq'::regclass);


--
-- Name: mobile_tokens id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mobile_tokens ALTER COLUMN id SET DEFAULT nextval('public.mobile_tokens_id_seq'::regclass);


--
-- Name: mpesa_b2b_transactions id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_b2b_transactions ALTER COLUMN id SET DEFAULT nextval('public.mpesa_b2b_transactions_id_seq'::regclass);


--
-- Name: mpesa_b2c_transactions id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_b2c_transactions ALTER COLUMN id SET DEFAULT nextval('public.mpesa_b2c_transactions_id_seq'::regclass);


--
-- Name: mpesa_c2b_transactions id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_c2b_transactions ALTER COLUMN id SET DEFAULT nextval('public.mpesa_c2b_transactions_id_seq'::regclass);


--
-- Name: mpesa_config id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_config ALTER COLUMN id SET DEFAULT nextval('public.mpesa_config_id_seq'::regclass);


--
-- Name: mpesa_transactions id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_transactions ALTER COLUMN id SET DEFAULT nextval('public.mpesa_transactions_id_seq'::regclass);


--
-- Name: network_devices id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.network_devices ALTER COLUMN id SET DEFAULT nextval('public.network_devices_id_seq'::regclass);


--
-- Name: onu_discovery_log id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.onu_discovery_log ALTER COLUMN id SET DEFAULT nextval('public.onu_discovery_log_id_seq'::regclass);


--
-- Name: onu_signal_history id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.onu_signal_history ALTER COLUMN id SET DEFAULT nextval('public.onu_signal_history_id_seq'::regclass);


--
-- Name: onu_uptime_log id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.onu_uptime_log ALTER COLUMN id SET DEFAULT nextval('public.onu_uptime_log_id_seq'::regclass);


--
-- Name: orders id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orders ALTER COLUMN id SET DEFAULT nextval('public.orders_id_seq'::regclass);


--
-- Name: payroll id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.payroll ALTER COLUMN id SET DEFAULT nextval('public.payroll_id_seq'::regclass);


--
-- Name: payroll_commissions id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.payroll_commissions ALTER COLUMN id SET DEFAULT nextval('public.payroll_commissions_id_seq'::regclass);


--
-- Name: performance_reviews id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.performance_reviews ALTER COLUMN id SET DEFAULT nextval('public.performance_reviews_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: products_services id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.products_services ALTER COLUMN id SET DEFAULT nextval('public.products_services_id_seq'::regclass);


--
-- Name: public_holidays id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.public_holidays ALTER COLUMN id SET DEFAULT nextval('public.public_holidays_id_seq'::regclass);


--
-- Name: purchase_order_items id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.purchase_order_items ALTER COLUMN id SET DEFAULT nextval('public.purchase_order_items_id_seq'::regclass);


--
-- Name: purchase_orders id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.purchase_orders ALTER COLUMN id SET DEFAULT nextval('public.purchase_orders_id_seq'::regclass);


--
-- Name: quote_items id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.quote_items ALTER COLUMN id SET DEFAULT nextval('public.quote_items_id_seq'::regclass);


--
-- Name: quotes id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.quotes ALTER COLUMN id SET DEFAULT nextval('public.quotes_id_seq'::regclass);


--
-- Name: role_permissions id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.role_permissions ALTER COLUMN id SET DEFAULT nextval('public.role_permissions_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: salary_advance_repayments id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.salary_advance_repayments ALTER COLUMN id SET DEFAULT nextval('public.salary_advance_repayments_id_seq'::regclass);


--
-- Name: salary_advances id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.salary_advances ALTER COLUMN id SET DEFAULT nextval('public.salary_advances_id_seq'::regclass);


--
-- Name: sales_commissions id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.sales_commissions ALTER COLUMN id SET DEFAULT nextval('public.sales_commissions_id_seq'::regclass);


--
-- Name: salespersons id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.salespersons ALTER COLUMN id SET DEFAULT nextval('public.salespersons_id_seq'::regclass);


--
-- Name: schema_migrations id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.schema_migrations ALTER COLUMN id SET DEFAULT nextval('public.schema_migrations_id_seq'::regclass);


--
-- Name: service_fee_types id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.service_fee_types ALTER COLUMN id SET DEFAULT nextval('public.service_fee_types_id_seq'::regclass);


--
-- Name: service_packages id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.service_packages ALTER COLUMN id SET DEFAULT nextval('public.service_packages_id_seq'::regclass);


--
-- Name: settings id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.settings ALTER COLUMN id SET DEFAULT nextval('public.settings_id_seq'::regclass);


--
-- Name: sla_business_hours id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.sla_business_hours ALTER COLUMN id SET DEFAULT nextval('public.sla_business_hours_id_seq'::regclass);


--
-- Name: sla_holidays id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.sla_holidays ALTER COLUMN id SET DEFAULT nextval('public.sla_holidays_id_seq'::regclass);


--
-- Name: sla_policies id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.sla_policies ALTER COLUMN id SET DEFAULT nextval('public.sla_policies_id_seq'::regclass);


--
-- Name: sms_logs id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.sms_logs ALTER COLUMN id SET DEFAULT nextval('public.sms_logs_id_seq'::regclass);


--
-- Name: tax_rates id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.tax_rates ALTER COLUMN id SET DEFAULT nextval('public.tax_rates_id_seq'::regclass);


--
-- Name: team_members id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.team_members ALTER COLUMN id SET DEFAULT nextval('public.team_members_id_seq'::regclass);


--
-- Name: teams id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.teams ALTER COLUMN id SET DEFAULT nextval('public.teams_id_seq'::regclass);


--
-- Name: technician_kit_items id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.technician_kit_items ALTER COLUMN id SET DEFAULT nextval('public.technician_kit_items_id_seq'::regclass);


--
-- Name: technician_kits id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.technician_kits ALTER COLUMN id SET DEFAULT nextval('public.technician_kits_id_seq'::regclass);


--
-- Name: ticket_categories id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_categories ALTER COLUMN id SET DEFAULT nextval('public.ticket_categories_id_seq'::regclass);


--
-- Name: ticket_comments id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_comments ALTER COLUMN id SET DEFAULT nextval('public.ticket_comments_id_seq'::regclass);


--
-- Name: ticket_commission_rates id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_commission_rates ALTER COLUMN id SET DEFAULT nextval('public.ticket_commission_rates_id_seq'::regclass);


--
-- Name: ticket_earnings id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_earnings ALTER COLUMN id SET DEFAULT nextval('public.ticket_earnings_id_seq'::regclass);


--
-- Name: ticket_escalations id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_escalations ALTER COLUMN id SET DEFAULT nextval('public.ticket_escalations_id_seq'::regclass);


--
-- Name: ticket_satisfaction_ratings id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_satisfaction_ratings ALTER COLUMN id SET DEFAULT nextval('public.ticket_satisfaction_ratings_id_seq'::regclass);


--
-- Name: ticket_service_fees id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_service_fees ALTER COLUMN id SET DEFAULT nextval('public.ticket_service_fees_id_seq'::regclass);


--
-- Name: ticket_sla_logs id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_sla_logs ALTER COLUMN id SET DEFAULT nextval('public.ticket_sla_logs_id_seq'::regclass);


--
-- Name: ticket_status_tokens id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_status_tokens ALTER COLUMN id SET DEFAULT nextval('public.ticket_status_tokens_id_seq'::regclass);


--
-- Name: ticket_templates id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_templates ALTER COLUMN id SET DEFAULT nextval('public.ticket_templates_id_seq'::regclass);


--
-- Name: tickets id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.tickets ALTER COLUMN id SET DEFAULT nextval('public.tickets_id_seq'::regclass);


--
-- Name: tr069_devices id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.tr069_devices ALTER COLUMN id SET DEFAULT nextval('public.tr069_devices_id_seq'::regclass);


--
-- Name: user_notifications id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.user_notifications ALTER COLUMN id SET DEFAULT nextval('public.user_notifications_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: vendor_bill_items id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendor_bill_items ALTER COLUMN id SET DEFAULT nextval('public.vendor_bill_items_id_seq'::regclass);


--
-- Name: vendor_bills id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendor_bills ALTER COLUMN id SET DEFAULT nextval('public.vendor_bills_id_seq'::regclass);


--
-- Name: vendor_payments id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendor_payments ALTER COLUMN id SET DEFAULT nextval('public.vendor_payments_id_seq'::regclass);


--
-- Name: vendors id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendors ALTER COLUMN id SET DEFAULT nextval('public.vendors_id_seq'::regclass);


--
-- Name: vlan_history id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vlan_history ALTER COLUMN id SET DEFAULT nextval('public.vlan_history_id_seq'::regclass);


--
-- Name: whatsapp_conversations id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.whatsapp_conversations ALTER COLUMN id SET DEFAULT nextval('public.whatsapp_conversations_id_seq'::regclass);


--
-- Name: whatsapp_logs id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.whatsapp_logs ALTER COLUMN id SET DEFAULT nextval('public.whatsapp_logs_id_seq'::regclass);


--
-- Name: whatsapp_messages id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.whatsapp_messages ALTER COLUMN id SET DEFAULT nextval('public.whatsapp_messages_id_seq'::regclass);


--
-- Name: wireguard_peers id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.wireguard_peers ALTER COLUMN id SET DEFAULT nextval('public.wireguard_peers_id_seq'::regclass);


--
-- Name: wireguard_servers id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.wireguard_servers ALTER COLUMN id SET DEFAULT nextval('public.wireguard_servers_id_seq'::regclass);


--
-- Name: wireguard_settings id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.wireguard_settings ALTER COLUMN id SET DEFAULT nextval('public.wireguard_settings_id_seq'::regclass);


--
-- Name: wireguard_subnets id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.wireguard_subnets ALTER COLUMN id SET DEFAULT nextval('public.wireguard_subnets_id_seq'::regclass);


--
-- Name: wireguard_sync_logs id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.wireguard_sync_logs ALTER COLUMN id SET DEFAULT nextval('public.wireguard_sync_logs_id_seq'::regclass);


--
-- Data for Name: accounting_settings; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.accounting_settings (id, setting_key, setting_value, updated_at) FROM stdin;
1	invoice_prefix	INV-	2025-12-10 12:07:39.475753
2	invoice_next_number	1001	2025-12-10 12:07:39.549009
3	quote_prefix	QUO-	2025-12-10 12:07:39.61989
4	quote_next_number	1001	2025-12-10 12:07:39.690701
5	po_prefix	PO-	2025-12-10 12:07:39.761437
6	po_next_number	1001	2025-12-10 12:07:39.832179
7	payment_prefix	PAY-	2025-12-10 12:07:39.903083
8	payment_next_number	1001	2025-12-10 12:07:39.974828
9	default_payment_terms	30	2025-12-10 12:07:40.046081
10	default_currency	KES	2025-12-10 12:07:40.116846
11	company_tax_pin		2025-12-10 12:07:40.18861
\.


--
-- Data for Name: activity_logs; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.activity_logs (id, user_id, action_type, entity_type, entity_id, entity_reference, details, ip_address, created_at) FROM stdin;
1	1	create	complaint	5	CMP-20251205-8598	Complaint received: Test Complaint Bug	127.0.0.1	2025-12-05 17:06:20.525947
2	1	create	complaint	6	CMP-20251205-3712	Complaint received: Test Complaint Bug	127.0.0.1	2025-12-05 17:07:38.574808
3	1	create	order	2	ORD20251205F4C7	Created order for: Test Customer	127.0.0.1	2025-12-05 17:52:36.069075
4	1	create	complaint	7	CMP-20251208-3026	Complaint received: fvfvfvf	172.31.85.2	2025-12-08 15:48:58.389991
5	1	create	complaint	8	CMP-20251208-2960	Complaint received: Test Complaint	127.0.0.1	2025-12-08 15:50:19.117156
\.


--
-- Data for Name: announcement_recipients; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.announcement_recipients (id, announcement_id, employee_id, sms_sent, sms_sent_at, notification_sent, notification_read, notification_read_at, created_at) FROM stdin;
\.


--
-- Data for Name: announcements; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.announcements (id, title, message, priority, target_audience, target_branch_id, target_team_id, send_sms, send_notification, scheduled_at, sent_at, status, created_by, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: attendance; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.attendance (id, employee_id, date, clock_in, clock_out, status, hours_worked, overtime_hours, notes, created_at, updated_at, late_minutes, source, clock_in_latitude, clock_in_longitude, clock_out_latitude, clock_out_longitude, clock_in_address, clock_out_address, deduction) FROM stdin;
\.


--
-- Data for Name: attendance_notification_logs; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.attendance_notification_logs (id, employee_id, notification_template_id, attendance_date, clock_in_time, late_minutes, deduction_amount, notification_type, phone, message, status, response_data, sent_at, created_at) FROM stdin;
\.


--
-- Data for Name: bill_reminders; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.bill_reminders (id, bill_id, reminder_date, sent_at, sent_to, notification_type, is_sent, created_at) FROM stdin;
\.


--
-- Data for Name: biometric_attendance_logs; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.biometric_attendance_logs (id, device_id, employee_id, device_user_id, log_time, log_type, verify_mode, raw_data, processed, attendance_id, created_at) FROM stdin;
\.


--
-- Data for Name: biometric_devices; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.biometric_devices (id, name, device_type, ip_address, port, username, password_encrypted, sync_interval_minutes, is_active, last_sync_at, last_sync_status, last_sync_message, created_at, updated_at, serial_number, api_base_url, last_transaction_id, company_name) FROM stdin;
1	AdminGPON	zkteco	192.168.1.250	4370	admin	f02seH8KVe65mCceYkOweclYkZ+K6u49eTXQPyN8c3E=	15	t	2025-12-04 02:26:55.25813	failed	No response from device	2025-12-03 18:20:53.012592	2025-12-04 02:26:55.25813	\N	\N	\N	\N
\.


--
-- Data for Name: branch_employees; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.branch_employees (id, branch_id, employee_id, is_primary, assigned_at) FROM stdin;
\.


--
-- Data for Name: branches; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.branches (id, name, code, address, phone, email, whatsapp_group, manager_id, is_active, created_at, updated_at) FROM stdin;
1	Head Office	HQ	\N	\N	\N	\N	\N	t	2025-12-09 10:42:32.676059	2025-12-09 10:42:32.676059
\.


--
-- Data for Name: chart_of_accounts; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.chart_of_accounts (id, code, name, type, category, description, parent_id, is_system, is_active, balance, created_at) FROM stdin;
1	1000	Assets	asset	Assets	\N	\N	t	t	0.00	2025-12-10 12:07:36.353071
2	1100	Cash	asset	Current Assets	\N	\N	t	t	0.00	2025-12-10 12:07:36.432039
3	1110	M-Pesa	asset	Current Assets	\N	\N	t	t	0.00	2025-12-10 12:07:36.503502
4	1120	Bank Account	asset	Current Assets	\N	\N	t	t	0.00	2025-12-10 12:07:36.574286
5	1200	Accounts Receivable	asset	Current Assets	\N	\N	t	t	0.00	2025-12-10 12:07:36.645088
6	1300	Inventory	asset	Current Assets	\N	\N	t	t	0.00	2025-12-10 12:07:36.716068
7	2000	Liabilities	liability	Liabilities	\N	\N	t	t	0.00	2025-12-10 12:07:36.786956
8	2100	Accounts Payable	liability	Current Liabilities	\N	\N	t	t	0.00	2025-12-10 12:07:36.857725
9	2200	VAT Payable	liability	Current Liabilities	\N	\N	t	t	0.00	2025-12-10 12:07:36.928545
10	3000	Equity	equity	Equity	\N	\N	t	t	0.00	2025-12-10 12:07:36.99976
11	3100	Owners Equity	equity	Equity	\N	\N	t	t	0.00	2025-12-10 12:07:37.070446
12	3200	Retained Earnings	equity	Equity	\N	\N	t	t	0.00	2025-12-10 12:07:37.141244
13	4000	Revenue	revenue	Revenue	\N	\N	t	t	0.00	2025-12-10 12:07:37.212038
14	4100	Service Revenue	revenue	Revenue	\N	\N	t	t	0.00	2025-12-10 12:07:37.282784
15	4200	Installation Revenue	revenue	Revenue	\N	\N	t	t	0.00	2025-12-10 12:07:37.35372
16	4300	Equipment Sales	revenue	Revenue	\N	\N	t	t	0.00	2025-12-10 12:07:37.424449
17	5000	Expenses	expense	Expenses	\N	\N	t	t	0.00	2025-12-10 12:07:37.495225
18	5100	Salaries & Wages	expense	Operating Expenses	\N	\N	t	t	0.00	2025-12-10 12:07:37.565937
19	5200	Rent	expense	Operating Expenses	\N	\N	t	t	0.00	2025-12-10 12:07:37.636722
20	5300	Utilities	expense	Operating Expenses	\N	\N	t	t	0.00	2025-12-10 12:07:37.707444
21	5400	Internet & Bandwidth	expense	Operating Expenses	\N	\N	t	t	0.00	2025-12-10 12:07:37.778147
22	5500	Equipment & Supplies	expense	Operating Expenses	\N	\N	t	t	0.00	2025-12-10 12:07:37.848968
23	5600	Marketing	expense	Operating Expenses	\N	\N	t	t	0.00	2025-12-10 12:07:37.919993
24	5700	Transport	expense	Operating Expenses	\N	\N	t	t	0.00	2025-12-10 12:07:37.990722
\.


--
-- Data for Name: company_settings; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.company_settings (id, setting_key, setting_value, setting_type, created_at, updated_at) FROM stdin;
1	advanta_api_key	QatrX+H72gaM8WEoLSTwWjo6Y1BYd1l3SE5ieXZETGc1RVFpeW9CdjhpZXp3QW5jNkVDWHBFNlMwWXBWb3FPTFdJeXhPS3RoUjNEY1lzelJ6QQ==	secret	2025-12-03 11:36:35.035975	2025-12-03 11:36:35.035975
2	advanta_partner_id	r2J0X+OLUTugnQLSS8AanDo6Tk9TTUMvUzJneWZ6REhoSmRIcnNZQT09	secret	2025-12-03 11:36:35.264867	2025-12-03 11:36:35.264867
3	advanta_shortcode	5113NrZ5edVXbXH49B01hDo6K253aFVJN2ZBNDlXQlVjYTdQN1Vydz09	secret	2025-12-03 11:36:35.487603	2025-12-03 11:36:35.487603
4	advanta_url	zGkbBA7gAmLBbhGYm3FZQjo6bVdmRS9CeW5SOFcydWQvQzluWUJVNlpqNkpIS1UyS3JWRmVoa3FFaGgzdjRNRkxEdFBLQldtdGdRS09jK2xnMzZUNERBMHBsaFU4eTFveVVZTDVwSnc9PQ==	secret	2025-12-03 11:36:35.710489	2025-12-03 11:36:35.710489
6	whatsapp_country_code	254	text	2025-12-08 11:47:23.156663	2025-12-08 11:47:23.156663
7	whatsapp_default_message		text	2025-12-08 11:47:23.365244	2025-12-08 11:47:23.365244
8	whatsapp_provider	session	text	2025-12-08 11:47:23.573223	2025-12-08 11:47:23.573223
9	whatsapp_meta_token		secret	2025-12-08 11:47:23.781153	2025-12-08 11:47:23.781153
10	whatsapp_phone_number_id		text	2025-12-08 11:47:23.988903	2025-12-08 11:47:23.988903
11	whatsapp_business_id		text	2025-12-08 11:47:24.196873	2025-12-08 11:47:24.196873
12	whatsapp_waha_url		text	2025-12-08 11:47:24.405058	2025-12-08 11:47:24.405058
13	whatsapp_waha_api_key		secret	2025-12-08 11:47:24.613252	2025-12-08 11:47:24.613252
14	whatsapp_ultramsg_instance		text	2025-12-08 11:47:24.821517	2025-12-08 11:47:24.821517
15	whatsapp_ultramsg_token		secret	2025-12-08 11:47:25.029448	2025-12-08 11:47:25.029448
16	whatsapp_custom_url		text	2025-12-08 11:47:25.237514	2025-12-08 11:47:25.237514
17	whatsapp_custom_api_key		secret	2025-12-08 11:47:25.446464	2025-12-08 11:47:25.446464
18	wa_template_status_update	Hi {customer_name},\n\nThis is an update on your ticket #{ticket_number}.\n\nCurrent Status: {status}\n\nWe're working on resolving your issue. Thank you for your patience.	text	2025-12-09 07:17:49.127911	2025-12-09 07:17:49.127911
19	wa_template_need_info	Hi {customer_name},\n\nRegarding ticket #{ticket_number}: {subject}\n\nWe need some additional information to proceed. Could you please provide more details?\n\nThank you.	text	2025-12-09 07:17:49.127911	2025-12-09 07:17:49.127911
20	wa_template_resolved	Hi {customer_name},\n\nGreat news! Your ticket #{ticket_number} has been resolved.\n\nIf you have any further questions or issues, please don't hesitate to contact us.\n\nThank you for choosing our services!	text	2025-12-09 07:17:49.127911	2025-12-09 07:17:49.127911
21	wa_template_technician_coming	Hi {customer_name},\n\nRegarding ticket #{ticket_number}:\n\nOur technician is on the way to your location. Please ensure someone is available to receive them.\n\nThank you.	text	2025-12-09 07:17:49.127911	2025-12-09 07:17:49.127911
22	wa_template_scheduled	Hi {customer_name},\n\nYour service visit for ticket #{ticket_number} has been scheduled.\n\nPlease confirm if this time works for you.\n\nThank you.	text	2025-12-09 07:17:49.127911	2025-12-09 07:17:49.127911
23	wa_template_order_confirmation	Hi {customer_name},\n\nThank you for your order #{order_number}!\n\nPackage: {package_name}\nAmount: KES {amount}\n\nWe will contact you shortly to schedule installation.\n\nThank you for choosing our services!	text	2025-12-09 07:17:49.127911	2025-12-09 07:17:49.127911
24	wa_template_order_processing	Hi {customer_name},\n\nYour order #{order_number} is being processed.\n\nOur team will contact you to schedule the installation.\n\nThank you!	text	2025-12-09 07:17:49.127911	2025-12-09 07:17:49.127911
25	wa_template_order_installation	Hi {customer_name},\n\nWe're ready to install your service for order #{order_number}.\n\nPlease let us know a convenient time for installation.\n\nThank you!	text	2025-12-09 07:17:49.127911	2025-12-09 07:17:49.127911
26	wa_template_complaint_received	Hi {customer_name},\n\nWe have received your complaint (Ref: {complaint_number}).\n\nCategory: {category}\n\nOur team will review and respond within 24 hours.\n\nThank you for your feedback.	text	2025-12-09 07:17:49.127911	2025-12-09 07:17:49.127911
27	wa_template_complaint_review	Hi {customer_name},\n\nRegarding your complaint {complaint_number}:\n\nWe are currently reviewing your issue and will update you soon.\n\nThank you for your patience.	text	2025-12-09 07:17:49.127911	2025-12-09 07:17:49.127911
28	wa_template_complaint_approved	Hi {customer_name},\n\nYour complaint {complaint_number} has been approved and a support ticket will be created.\n\nOur team will contact you shortly to resolve the issue.\n\nThank you!	text	2025-12-09 07:17:49.127911	2025-12-09 07:17:49.127911
29	wa_template_complaint_rejected	Hi {customer_name},\n\nRegarding your complaint {complaint_number}:\n\nAfter careful review, we were unable to proceed with this complaint.\n\nIf you have any questions, please contact our support team.\n\nThank you.	text	2025-12-09 07:17:49.127911	2025-12-09 07:17:49.127911
5	whatsapp_enabled	1	text	2025-12-08 11:47:22.941156	2025-12-08 11:47:22.941156
30	oneisp_api_token	eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyIjp7InVzZXJJZCI6IjF0SGdNUmcyOFYwUnhhVlNRVUdFWkFQV0psQiIsInVzZXJUeXBlIjoiSVNQIiwiaGFzTGljZW5jZSI6dHJ1ZX0sImV4cCI6MTc2NTU3NzMxOCwiaWF0IjoxNzY1NTU1NzE4fQ.LmEvC14PLWqNkUJXIfa6knEuuboILad0EUe6bTbCzsE	text	2025-12-12 16:54:07.968788	2025-12-12 16:54:07.968788
31	timezone	Africa/Nairobi	text	2025-12-19 16:56:28.186625	2025-12-19 16:56:28.186625
\.


--
-- Data for Name: complaints; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.complaints (id, complaint_number, customer_id, customer_name, customer_phone, customer_email, customer_location, category, subject, description, status, priority, reviewed_by, reviewed_at, review_notes, converted_ticket_id, source, created_at, updated_at, created_by) FROM stdin;
1	CMP-20251205-4899	\N	Test Customer	0712345678	test@test.com	Nairobi	connectivity	Test Complaint	Testing the new complaint system\n\nLocation: Nairobi\n\nSubmitted via: Public Complaint Form	pending	medium	\N	\N	\N	\N	public	2025-12-05 07:06:11.750079	2025-12-05 07:06:11.750079	\N
2	CMP-20251205-1697	2	Test User	0712345679	test2@test.com	Westlands	billing	Billing Problem	I need help with my bill\n\nLocation: Westlands\n\nSubmitted via: Public Complaint Form	converted	medium	\N	2025-12-05 07:18:37.266281	\N	4	public	2025-12-05 07:18:25.087845	2025-12-05 07:23:04.818496	\N
3	CMP-20251205-0461	\N	John Doe	0722123456	john@test.com	CBD	speed	Slow Internet Speed	My connection is very slow\n\nLocation: CBD\n\nSubmitted via: Public Complaint Form	pending	medium	\N	\N	\N	\N	public	2025-12-05 07:23:42.076883	2025-12-05 07:23:42.076883	\N
4	CMP-20251205-5622	\N	Test User	0712345678	\N	\N	connectivity	Test Complaint	Test description\n\nSubmitted via: Public Complaint Form	pending	medium	\N	\N	\N	\N	public	2025-12-05 17:05:45.969833	2025-12-05 17:05:45.969833	1
5	CMP-20251205-8598	\N	Test User	0712345678	\N	\N	connectivity	Test Complaint Bug	Testing complaint submission\n\nSubmitted via: Public Complaint Form	pending	medium	\N	\N	\N	\N	public	2025-12-05 17:06:19.908693	2025-12-05 17:06:19.908693	1
6	CMP-20251205-3712	\N	Test User	0712345678	\N	\N	connectivity	Test Complaint Bug	Testing complaint submission\n\nSubmitted via: Public Complaint Form	pending	medium	\N	\N	\N	\N	public	2025-12-05 17:07:37.957768	2025-12-05 17:07:37.957768	1
7	CMP-20251208-3026	\N	fvfvfvfvf	0701031531	\N	fvfvfvfvf	connectivity	fvfvfvf	fvfvfvfv\n\nLocation: fvfvfvfvf\n\nSubmitted via: Public Complaint Form	pending	medium	\N	\N	\N	\N	public	2025-12-08 15:48:57.667901	2025-12-08 15:48:57.667901	1
8	CMP-20251208-2960	\N	Test User	0700000000	\N	\N	other	Test Complaint	This is a test\n\nSubmitted via: Public Complaint Form	pending	medium	\N	\N	\N	\N	public	2025-12-08 15:50:18.420738	2025-12-08 15:50:18.420738	1
\.


--
-- Data for Name: customer_payments; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.customer_payments (id, payment_number, customer_id, invoice_id, payment_date, amount, payment_method, mpesa_transaction_id, mpesa_receipt, reference, notes, status, created_by, created_at) FROM stdin;
\.


--
-- Data for Name: customer_ticket_tokens; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.customer_ticket_tokens (id, ticket_id, customer_id, token_hash, token_lookup, expires_at, max_uses, used_count, created_at, last_used_at, is_active) FROM stdin;
\.


--
-- Data for Name: customers; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.customers (id, account_number, name, email, phone, address, service_plan, connection_status, installation_date, notes, created_at, updated_at, created_by, username, billing_id) FROM stdin;
1	ISP-2025-03531	Martin Muriu		+254707256700	LOS	basic	active	2025-12-03		2025-12-03 10:51:11.943791	2025-12-03 10:51:11.943791	\N	\N	\N
2	CMP-20251205-7776	Test User	test2@test.com	0712345679	Westlands	Complaint	active	\N	\N	2025-12-05 07:23:04.818496	2025-12-05 07:23:04.818496	\N	\N	\N
\.


--
-- Data for Name: departments; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.departments (id, name, description, manager_id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: device_interfaces; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.device_interfaces (id, device_id, if_index, if_name, if_descr, if_type, if_speed, if_status, in_octets, out_octets, in_errors, out_errors, last_updated) FROM stdin;
\.


--
-- Data for Name: device_monitoring_log; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.device_monitoring_log (id, device_id, metric_type, metric_name, metric_value, recorded_at) FROM stdin;
\.


--
-- Data for Name: device_onus; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.device_onus (id, device_id, onu_id, serial_number, mac_address, pon_port, slot, port, onu_index, customer_id, status, rx_power, tx_power, distance, description, profile, last_online, last_offline, last_polled, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: device_user_mapping; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.device_user_mapping (id, device_id, device_user_id, employee_id, created_at) FROM stdin;
\.


--
-- Data for Name: device_vlans; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.device_vlans (id, device_id, vlan_id, vlan_name, vlan_status, ports, tagged_ports, untagged_ports, in_octets, out_octets, in_rate, out_rate, last_updated) FROM stdin;
\.


--
-- Data for Name: employee_branches; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.employee_branches (id, employee_id, branch_id, is_primary, assigned_at, assigned_by) FROM stdin;
\.


--
-- Data for Name: employees; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.employees (id, employee_id, user_id, name, email, phone, department_id, "position", salary, hire_date, employment_status, emergency_contact, emergency_phone, address, notes, created_at, updated_at) FROM stdin;
1	EMP-2025-8093	3	Martin Muriu	support@superlite.co.ke	0701031531	\N	CEO	\N	\N	active	\N	\N	\N	\N	2025-12-03 11:42:23.932236	2025-12-03 11:50:23.09861
6	EMP-2025-0001	1	Admin User	admin@isp.com	0700000001	\N	Administrator	\N	\N	active	\N	\N	\N	\N	2025-12-05 15:46:13.802945	2025-12-05 15:46:13.802945
7	EMP-2025-0002	2	John Tech	john@isp.com	0700000002	\N	Technician	\N	\N	active	\N	\N	\N	\N	2025-12-05 15:46:18.420251	2025-12-05 15:46:18.420251
8	EMP-2025-2147	5	john muthee	john@superlite.co.ke	0767908989	\N	Salesperson	\N	\N	active					2025-12-05 18:56:13.094729	2025-12-05 18:56:13.094729
\.


--
-- Data for Name: equipment; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.equipment (id, category_id, name, brand, model, serial_number, mac_address, purchase_date, purchase_price, warranty_expiry, condition, status, location, notes, created_at, updated_at, warehouse_id, location_id, quantity, sku, barcode, lifecycle_status, last_lifecycle_change, installed_customer_id, installed_at, installed_by, min_stock_level, max_stock_level, reorder_point, unit_cost) FROM stdin;
1	1	homerouter					\N	\N	\N	new	assigned			2025-12-04 02:24:32.470756	2025-12-04 02:25:21.452118	\N	\N	1	\N	\N	in_stock	\N	\N	\N	\N	0	0	0	0.00
\.


--
-- Data for Name: equipment_assignments; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.equipment_assignments (id, equipment_id, employee_id, assigned_date, return_date, assigned_by, notes, status, created_at, customer_id) FROM stdin;
1	1	1	2025-12-04	\N	1		assigned	2025-12-04 02:25:21.452118	\N
\.


--
-- Data for Name: equipment_categories; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.equipment_categories (id, name, description, created_at, parent_id, item_type) FROM stdin;
1	Router	Network routers and access points	2025-12-03 13:59:19.813469	\N	serialized
2	Modem	DSL, fiber, and cable modems	2025-12-03 13:59:19.813469	\N	serialized
3	Switch	Network switches	2025-12-03 13:59:19.813469	\N	serialized
4	Cable	Ethernet cables, fiber cables, patch cords	2025-12-03 13:59:19.813469	\N	serialized
5	Antenna	Wireless antennas and dishes	2025-12-03 13:59:19.813469	\N	serialized
6	Power Equipment	UPS, power supplies, surge protectors	2025-12-03 13:59:19.813469	\N	serialized
7	Tools	Installation and maintenance tools	2025-12-03 13:59:19.813469	\N	serialized
8	Other	Miscellaneous equipment	2025-12-03 13:59:19.813469	\N	serialized
9	Core Network	Routers, switches, OLTs, servers, and core infrastructure	2025-12-16 21:50:10.491977	\N	serialized
10	CPE	Customer Premises Equipment - ONTs, modems, routers for customers	2025-12-16 21:50:10.491977	\N	serialized
11	Consumables	Cables, connectors, clips, ties - items used up during installation	2025-12-16 21:50:10.491977	\N	bulk
12	Spares & Repairs	Replacement parts and items pending repair	2025-12-16 21:50:10.491977	\N	serialized
\.


--
-- Data for Name: equipment_faults; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.equipment_faults (id, equipment_id, reported_date, reported_by, fault_description, severity, repair_status, repair_date, repair_cost, repair_notes, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: equipment_lifecycle_logs; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.equipment_lifecycle_logs (id, equipment_id, from_status, to_status, changed_by, reference_type, reference_id, notes, created_at) FROM stdin;
\.


--
-- Data for Name: equipment_loans; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.equipment_loans (id, equipment_id, customer_id, loan_date, expected_return_date, actual_return_date, loaned_by, deposit_amount, deposit_paid, notes, status, created_at) FROM stdin;
\.


--
-- Data for Name: expense_categories; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.expense_categories (id, name, description, account_id, is_active, created_at) FROM stdin;
1	Salaries	\N	\N	t	2025-12-10 12:07:38.413746
2	Rent	\N	\N	t	2025-12-10 12:07:38.485909
3	Utilities	\N	\N	t	2025-12-10 12:07:38.556687
4	Internet & Bandwidth	\N	\N	t	2025-12-10 12:07:38.627446
5	Equipment	\N	\N	t	2025-12-10 12:07:38.698173
6	Office Supplies	\N	\N	t	2025-12-10 12:07:38.768983
7	Transport	\N	\N	t	2025-12-10 12:07:38.839786
8	Marketing	\N	\N	t	2025-12-10 12:07:38.910594
9	Repairs & Maintenance	\N	\N	t	2025-12-10 12:07:38.983383
10	Other	\N	\N	t	2025-12-10 12:07:39.055071
\.


--
-- Data for Name: expenses; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.expenses (id, expense_number, category_id, vendor_id, expense_date, amount, tax_amount, total_amount, payment_method, reference, description, receipt_url, status, approved_by, approved_at, employee_id, created_by, created_at) FROM stdin;
\.


--
-- Data for Name: hr_notification_templates; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.hr_notification_templates (id, name, category, event_type, subject, sms_template, email_template, is_active, send_sms, send_email, created_at, updated_at) FROM stdin;
1	Late Arrival Notification	attendance	late_arrival	Late Arrival Notice	Dear {employee_name}, You checked in at {clock_in_time} which is {late_minutes} minutes late. Your work start time is {work_start_time}. A deduction of {deduction_amount} {currency} will be applied. - {company_name}	\N	t	t	f	2025-12-04 08:16:49.188258	2025-12-04 08:16:49.188258
2	Leave Request Created	leave	leave_request_created	New Leave Request	Leave request from {employee_name} for {total_days} days ({leave_type}) from {start_date} to {end_date}. Reason: {reason}. Please review in CRM. - {company_name}	\N	t	t	f	2025-12-11 07:30:55.235868	2025-12-11 07:30:55.235868
3	Leave Request Approved	leave	leave_approved	Leave Request Approved	Dear {employee_name}, Your leave request for {total_days} days ({leave_type}) from {start_date} to {end_date} has been APPROVED. Enjoy your leave! - {company_name}	\N	t	t	f	2025-12-11 07:30:55.235868	2025-12-11 07:30:55.235868
4	Leave Request Rejected	leave	leave_rejected	Leave Request Rejected	Dear {employee_name}, Your leave request for {total_days} days ({leave_type}) from {start_date} to {end_date} has been REJECTED. Reason: {rejection_reason}. - {company_name}	\N	t	t	f	2025-12-11 07:30:55.235868	2025-12-11 07:30:55.235868
5	Salary Advance Request Created	advance	advance_request_created	New Salary Advance Request	Salary advance request from {employee_name} for {currency} {amount}. Reason: {reason}. Repayment: {repayment_installments} installments. Please review in CRM. - {company_name}	\N	t	t	f	2025-12-11 07:30:55.235868	2025-12-11 07:30:55.235868
6	Salary Advance Approved	advance	advance_approved	Salary Advance Approved	Dear {employee_name}, Your salary advance request for {currency} {amount} has been APPROVED. Repayment: {currency} {repayment_amount} per {repayment_type}. - {company_name}	\N	t	t	f	2025-12-11 07:30:55.235868	2025-12-11 07:30:55.235868
7	Salary Advance Rejected	advance	advance_rejected	Salary Advance Rejected	Dear {employee_name}, Your salary advance request for {currency} {amount} has been REJECTED. {rejection_reason} - {company_name}	\N	t	t	f	2025-12-11 07:30:55.235868	2025-12-11 07:30:55.235868
8	Salary Advance Disbursed	advance	advance_disbursed	Salary Advance Disbursed	Dear {employee_name}, Your salary advance of {currency} {amount} has been disbursed. First deduction: {next_deduction_date}. Balance: {currency} {balance}. - {company_name}	\N	t	t	f	2025-12-11 07:30:55.235868	2025-12-11 07:30:55.235868
\.


--
-- Data for Name: huawei_alerts; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_alerts (id, olt_id, onu_id, alert_type, severity, title, message, is_read, is_resolved, resolved_at, resolved_by, created_at) FROM stdin;
\.


--
-- Data for Name: huawei_apartments; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_apartments (id, zone_id, subzone_id, name, address, floors, units_count, is_active, created_at, updated_at) FROM stdin;
1	2	1	Harmony		\N	\N	t	2025-12-20 16:01:49.201837	2025-12-20 16:01:49.201837
\.


--
-- Data for Name: huawei_boards; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_boards (id, olt_id, slot, board_name, board_type, status, hardware_version, software_version, onu_count, synced_at, created_at) FROM stdin;
\.


--
-- Data for Name: huawei_odb_units; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_odb_units (id, zone_id, subzone_id, apartment_id, code, capacity, ports_used, location_description, latitude, longitude, is_active, created_at, updated_at) FROM stdin;
1	2	\N	1	FAT36	8	0		\N	\N	t	2025-12-20 16:02:08.293566	2025-12-20 16:02:08.293566
\.


--
-- Data for Name: huawei_olt_boards; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_olt_boards (id, olt_id, slot, board_name, status, subtype, online_status, port_count, created_at, updated_at, hardware_version, software_version, serial_number, board_type, is_enabled, temperature) FROM stdin;
\.


--
-- Data for Name: huawei_olt_pon_ports; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_olt_pon_ports (id, olt_id, port_name, port_type, admin_status, oper_status, onu_count, created_at, updated_at, description, service_profile_id, line_profile_id, native_vlan, allowed_vlans, max_onus) FROM stdin;
\.


--
-- Data for Name: huawei_olt_uplinks; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_olt_uplinks (id, olt_id, port_name, port_type, admin_status, oper_status, speed, duplex, vlan_mode, pvid, created_at, updated_at, description, allowed_vlans, native_vlan, is_enabled, mtu, rx_bytes, tx_bytes, rx_errors, tx_errors, stats_updated_at) FROM stdin;
\.


--
-- Data for Name: huawei_olt_vlans; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_olt_vlans (id, olt_id, vlan_id, vlan_type, description, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: huawei_olts; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_olts (id, name, ip_address, port, connection_type, username, password_encrypted, snmp_community, snmp_version, snmp_port, vendor, model, location, is_active, last_sync_at, last_status, uptime, temperature, created_at, updated_at, boards_synced_at, vlans_synced_at, ports_synced_at, uplinks_synced_at, firmware_version, hardware_model, software_version, cpu_usage, memory_usage, system_synced_at, snmp_last_poll, snmp_sys_name, snmp_sys_descr, snmp_sys_uptime, snmp_sys_location, snmp_status, snmp_read_community, snmp_write_community, branch_id, smartolt_id) FROM stdin;
2	OneISP OLT	102.205.239.85	8384	telnet	oneisp	OQrjUxhAAWJBFbf5Zt+Y2kRoUWV5bEVTRHorcUhnTEk2a0xUUkE9PQ==	vsbmOaz3Xg1y	v2c	161	Huawei	MA5683T	\N	t	2025-12-22 10:46:34.091093	online	58 days, 20:30:46	\N	2025-12-22 10:38:20.013901	2025-12-22 10:38:20.013901	\N	\N	\N	\N	MA5600V800R015C00	\N	SPH106 HP1013	\N	\N	\N	2025-12-24 19:44:45.698697	\N	\N	\N	\N	offline	nchpu6stZ0Kw	d7i8NFvlthsf	\N	\N
1	Demo OLT	192.168.1.100	23	telnet	admin	demo_encrypted_pass	public	v2c	161	Huawei	MA5683T	\N	t	\N	online	\N	\N	2025-12-20 15:26:16.586925	2025-12-20 15:26:16.586925	\N	\N	\N	\N	V800R021C00	\N	\N	\N	\N	\N	2025-12-24 19:44:45.700061	MA5680T-Demo	Huawei Integrated Access Software. MA5680T V800R021C00SPC100	45 days, 12:34:56	Data Center Rack A1	simulated	\N	\N	\N	\N
\.


--
-- Data for Name: huawei_onu_mgmt_ips; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_onu_mgmt_ips (id, olt_id, onu_id, ip_address, subnet_mask, gateway, vlan_id, ip_type, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: huawei_onu_tr069_config; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_onu_tr069_config (onu_id, config_data, status, created_at, updated_at, applied_at) FROM stdin;
\.


--
-- Data for Name: huawei_onu_types; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_onu_types (id, name, model, model_aliases, vendor, eth_ports, pots_ports, wifi_capable, wifi_dual_band, catv_port, usb_port, pon_type, default_mode, tcont_count, gemport_count, recommended_line_profile, recommended_srv_profile, omci_capable, tr069_capable, description, is_active, created_at, updated_at) FROM stdin;
1	HG8010H	HG8010H	HG8010,EchoLife-HG8010H	Huawei	1	0	f	f	f	f	GPON	bridge	1	1	\N	\N	t	t	Single GE port SFU bridge - most basic FTTH terminal	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
2	HG8310M	HG8310M	HG8310,EchoLife-HG8310M	Huawei	1	0	f	f	f	f	GPON	bridge	1	1	\N	\N	t	t	Single GE port compact SFU bridge	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
3	HG8012H	HG8012H	HG8012,EchoLife-HG8012H	Huawei	1	1	f	f	f	f	GPON	bridge	1	1	\N	\N	t	t	Single GE + 1 POTS bridge ONT	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
4	HG8040H	HG8040H	HG8040,EchoLife-HG8040H	Huawei	4	0	f	f	f	f	GPON	bridge	1	1	\N	\N	t	t	4x GE bridge ONT without WiFi	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
5	HG8240H	HG8240H	HG8240,EchoLife-HG8240H	Huawei	4	2	f	f	f	f	GPON	router	1	1	\N	\N	t	t	4x GE + 2 POTS router without WiFi	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
6	HG8040F	HG8040F	EchoLife-HG8040F	Huawei	4	0	f	f	f	f	GPON	bridge	1	1	\N	\N	t	t	4x FE bridge ONT	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
7	HG8145V	HG8145V	HG8145,EchoLife-HG8145V	Huawei	4	1	t	f	f	t	GPON	router	1	1	\N	\N	t	t	4x GE + 1 POTS + WiFi 2.4GHz + USB	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
8	HG8145V5	HG8145V5	HG8145V5,EchoLife-HG8145V5,EG8145V5	Huawei	4	1	t	t	f	t	GPON	router	1	1	\N	\N	t	t	4x GE + 1 POTS + Dual-band WiFi + USB - Popular model	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
9	HG8145X6	HG8145X6	EchoLife-HG8145X6	Huawei	4	1	t	t	f	t	GPON	router	1	1	\N	\N	t	t	4x GE + 1 POTS + WiFi 6 Dual-band + USB	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
10	HG8245H	HG8245H	HG8245,EchoLife-HG8245H	Huawei	4	2	t	f	f	t	GPON	router	1	1	\N	\N	t	t	4x GE + 2 POTS + WiFi 2.4GHz + USB - Classic model	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
11	HG8245H5	HG8245H5	HG8245H5,EchoLife-HG8245H5	Huawei	4	2	t	t	f	t	GPON	router	1	1	\N	\N	t	t	4x GE + 2 POTS + Dual-band WiFi + USB	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
12	HG8245Q	HG8245Q	HG8245Q2,EchoLife-HG8245Q	Huawei	4	2	t	f	f	t	GPON	router	1	1	\N	\N	t	t	4x GE + 2 POTS + WiFi + CATV port	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
13	HG8245W5	HG8245W5	EchoLife-HG8245W5	Huawei	4	2	t	t	f	t	GPON	router	1	1	\N	\N	t	t	4x GE + 2 POTS + Dual-band WiFi AC + USB	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
14	HG8245X6	HG8245X6	EchoLife-HG8245X6	Huawei	4	2	t	t	f	t	GPON	router	1	1	\N	\N	t	t	4x GE + 2 POTS + WiFi 6 AX + USB - Latest high-end	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
16	HG8546V	HG8546V	EchoLife-HG8546V	Huawei	4	1	t	f	f	t	GPON	router	1	1	\N	\N	t	t	1x GE + 3x FE + 1 POTS + WiFi	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
17	HG8546V5	HG8546V5	EchoLife-HG8546V5	Huawei	4	1	t	t	f	t	GPON	router	1	1	\N	\N	t	t	1x GE + 3x FE + 1 POTS + Dual-band WiFi	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
18	EG8145V5	EG8145V5	EchoLife-EG8145V5	Huawei	4	1	t	t	f	t	GPON	router	1	1	\N	\N	t	t	4x GE + 1 POTS + Dual-band WiFi + USB - Advanced gateway	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
19	EG8245H5	EG8245H5	EchoLife-EG8245H5	Huawei	4	2	t	t	f	t	GPON	router	1	1	\N	\N	t	t	4x GE + 2 POTS + Dual-band WiFi - Premium gateway	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
20	EG8247H5	EG8247H5	EchoLife-EG8247H5	Huawei	4	2	t	t	f	t	GPON	router	1	1	\N	\N	t	t	4x GE + 2 POTS + Dual-band WiFi + CATV	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
21	HN8245Q	HN8245Q	EchoLife-HN8245Q	Huawei	4	2	t	f	f	t	GPON	router	1	1	\N	\N	t	t	4x GE + 2 POTS + WiFi + CATV - Business grade	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
22	HN8346Q	HN8346Q	EchoLife-HN8346Q	Huawei	4	2	t	t	f	t	GPON	router	1	1	\N	\N	t	t	4x 2.5GE + 2 POTS + WiFi 6 - Enterprise ONT	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
23	HS8145V	HS8145V	EchoLife-HS8145V	Huawei	4	1	t	f	f	t	GPON	router	1	1	\N	\N	t	t	4x GE + 1 POTS + WiFi - Smart home ready	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
24	HS8145V5	HS8145V5	EchoLife-HS8145V5	Huawei	4	1	t	t	f	t	GPON	router	1	1	\N	\N	t	t	4x GE + 1 POTS + Dual-band WiFi - Smart home	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
25	HS8546V	HS8546V	EchoLife-HS8546V	Huawei	4	1	t	f	f	t	GPON	router	1	1	\N	\N	t	t	1x GE + 3x FE + 1 POTS + WiFi - Smart home budget	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
26	HS8546V5	HS8546V5	EchoLife-HS8546V5	Huawei	4	1	t	t	f	t	GPON	router	1	1	\N	\N	t	t	1x GE + 3x FE + 1 POTS + Dual-band - Smart home budget	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
27	OptiXstar HN8255Ws	HN8255Ws	OptiXstar-HN8255Ws	Huawei	4	2	t	t	f	t	GPON	router	1	1	\N	\N	t	t	4x 2.5GE + 2 POTS + WiFi 6E - Premium 10G ready	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
28	OptiXstar K662c	K662c	OptiXstar-K662c	Huawei	4	2	t	t	f	t	GPON	router	1	1	\N	\N	t	t	4x GE + 2 POTS + WiFi 6 - Next-gen ONT	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
15	HG8546M	HG8546M	HG8546,EchoLife-HG8546M	Huawei	4	1	t	f	f	t	GPON	router	1	1	2	3	t	t	1x GE + 3x FE + 1 POTS + WiFi 2.4GHz - Popular budget model	t	2025-12-23 20:08:24.312229	2025-12-23 20:08:24.312229
\.


--
-- Data for Name: huawei_onus; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_onus (id, olt_id, customer_id, sn, name, description, frame, slot, port, onu_id, onu_type, mac_address, status, rx_power, tx_power, distance, last_down_cause, last_down_time, last_up_time, service_profile_id, line_profile, srv_profile, is_authorized, firmware_version, hardware_version, software_version, ip_address, config_state, run_state, auth_type, password, additional_info, created_at, updated_at, vlan_id, vlan_priority, ip_mode, line_profile_id, srv_profile_id, tr069_profile_id, zone, area, customer_name, auth_date, apartment, odb, zone_id, subzone_id, apartment_id, odb_id, olt_sync_pending, optical_updated_at, onu_type_id, tr069_device_id, tr069_serial, tr069_ip, tr069_status, tr069_last_inform, discovered_eqid, port_config, smartolt_external_id) FROM stdin;
\.


--
-- Data for Name: huawei_pon_ports; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_pon_ports (id, olt_id, frame, slot, port, admin_status, oper_status, onu_count, max_onus, description, synced_at, created_at) FROM stdin;
\.


--
-- Data for Name: huawei_port_vlans; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_port_vlans (id, olt_id, port_name, port_type, vlan_id, vlan_mode, created_at) FROM stdin;
\.


--
-- Data for Name: huawei_provisioning_logs; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_provisioning_logs (id, olt_id, onu_id, action, status, message, details, command_sent, command_response, user_id, created_at) FROM stdin;
1	1	\N	command	failed	Connection failed: Connection timed out		display ont autofind all		1	2025-12-20 15:29:01.376824
2	1	\N	command	failed	Connection failed: Connection timed out		display ont autofind all		1	2025-12-20 15:36:33.963016
3	1	\N	command	failed	Connection failed: Connection timed out		display ont autofind all		1	2025-12-20 15:39:05.430771
4	1	\N	command	failed	Connection failed: Connection timed out		display ont autofind all		1	2025-12-20 15:43:55.59672
5	1	\N	command	failed	Connection failed: Connection timed out		display ont autofind all		1	2025-12-20 15:45:01.645052
6	1	\N	command	failed	Connection failed: Connection timed out		display ont autofind all		1	2025-12-20 15:46:07.533335
7	1	\N	command	failed	Connection failed: Connection timed out		display ont autofind all		1	2025-12-20 15:47:18.405578
8	2	\N	command	failed	Authentication failed - invalid credentials		display version		\N	2025-12-22 11:06:53.180782
9	2	\N	command	success	Command executed		display version	display version\r\n{ <cr>|backplane<K>|frameid/slotid<S><Length 1-15> }:\r\n\r\n  Command:\r\n          display version \r\n\r\n  VERSION : MA5600V800R015C00\r\n  PATCH   : SPH106 HP1013\r\n  PRODUCT : MA5683T\r\n\r\n  Active Mainboard Running Area Information: \r\n  --------------------------------------------------\r\n  Current Program Area : Area A \r\n  Current Data Area : Area B \r\n\r\n  Program Area A Version : MA5600V800R015C00 \r\n  Program Area B Version : MA5600V800R015C00 \r\n\r\n  Data Area A Version : MA5600V800R015C00 \r\n  Data Area B Version : MA5600V800R015C00 \r\n  --------------------------------------------------\r\n\r\n  Uptime is 58 day(s), 20 hour(s), 53 minute(s), 2 second(s)\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:08:29.870021
10	2	\N	command	success	Command executed		display vlan all	display vlan all\r\n{ <cr>|vlanattr<K>|vlantype<E><mux,standard,smart,super> }:\r\n\r\n  Command:\r\n          display vlan all \r\n  -----------------------------------------------------------------------\r\n  VLAN   Type      Attribute  STND-Port NUM   SERV-Port NUM  VLAN-Con NUM\r\n  -----------------------------------------------------------------------\r\n     1   smart     common                 6               0             -\r\n    10   smart     common                 0               0             -\r\n    15   smart     common                 1             197             -\r\n    16   smart     common                 1              24             -\r\n    33   smart     common                 1               6             -\r\n    34   smart     common                 1             122             -\r\n    36   smart     common                 1               2             -\r\n    69   smart     common                 1             205             -\r\n   200   smart     common                 1               0             -\r\n   302   smart     common                 1               0             -\r\n   660   smart     common                 1               2             -\r\n   903   smart     common                 1               1             -\r\n   999   smart     common                 0               0             -\r\n  -----------------------------------------------------------------------\r\n  Total: 13\r\n  Note : STND-Port--standard port, SERV-Port--service virtual port,\r\n         VLAN-Con--vlan-connect\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:08:58.865992
11	2	\N	command	success	Command executed		vlan 888 smart	vlan 888 smart\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:09:29.843724
12	2	\N	command	success	Command executed		vlan desc 888 description "Test VLAN"	vlan desc 888 description "Test VLAN"\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:09:43.904799
13	2	\N	create_vlan	success	VLAN 888 created		vlan 888 smart	vlan 888 smart\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:09:44.374168
14	2	\N	command	success	Command executed		display vlan 888	display vlan 888\r\n{ <cr>|inner-vlan<K>|to<K> }:\r\n\r\n  Command:\r\n          display vlan 888 \r\n  VLAN ID: 888\r\n  VLAN name: VLAN_0888\r\n  VLAN type: smart\r\n  VLAN attribute: common\r\n  VLAN description: Test VLAN\r\n  VLAN forwarding mode in control board: VLAN-MAC\r\n  VLAN forwarding mode: VLAN-MAC\r\n  VLAN broadcast packet forwarding policy: forward\r\n  VLAN unknown multicast packet forwarding policy: forward\r\n  VLAN unknown unicast packet forwarding policy: forward\r\n  VLAN bind service profile ID: -\r\n  VLAN bind RAIO profile index: -\r\n  VLAN priority: -\r\n  Standard port number: 0\r\n  Service virtual port number: 0\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:09:59.886237
15	2	\N	command	success	Command executed		display vlan 888	display vlan 888\r\n{ <cr>|inner-vlan<K>|to<K> }:\r\n\r\n  Command:\r\n          display vlan 888 \r\n  VLAN ID: 888\r\n  VLAN name: VLAN_0888\r\n  VLAN type: smart\r\n  VLAN attribute: common\r\n  VLAN description: Test VLAN\r\n  VLAN forwarding mode in control board: VLAN-MAC\r\n  VLAN forwarding mode: VLAN-MAC\r\n  VLAN broadcast packet forwarding policy: forward\r\n  VLAN unknown multicast packet forwarding policy: forward\r\n  VLAN unknown unicast packet forwarding policy: forward\r\n  VLAN bind service profile ID: -\r\n  VLAN bind RAIO profile index: -\r\n  VLAN priority: -\r\n  Standard port number: 0\r\n  Service virtual port number: 0\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:10:43.837321
16	2	\N	command	success	Command executed		display vlan all	display vlan all\r\n{ <cr>|vlanattr<K>|vlantype<E><mux,standard,smart,super> }:\r\n\r\n  Command:\r\n          display vlan all \r\n  -----------------------------------------------------------------------\r\n  VLAN   Type      Attribute  STND-Port NUM   SERV-Port NUM  VLAN-Con NUM\r\n  -----------------------------------------------------------------------\r\n     1   smart     common                 6               0             -\r\n    10   smart     common                 0               0             -\r\n    15   smart     common                 1             197             -\r\n    16   smart     common                 1              24             -\r\n    33   smart     common                 1               6             -\r\n    34   smart     common                 1             122             -\r\n    36   smart     common                 1               2             -\r\n    69   smart     common                 1             205             -\r\n   200   smart     common                 1               0             -\r\n   302   smart     common                 1               0             -\r\n   660   smart     common                 1               2             -\r\n   888   smart     common                 0               0             -\r\n   903   smart     common                 1               1             -\r\n   999   smart     common                 0               0             -\r\n  -----------------------------------------------------------------------\r\n  Total: 14\r\n  Note : STND-Port--standard port, SERV-Port--service virtual port,\r\n         VLAN-Con--vlan-connect\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:10:58.889262
17	2	\N	sync_vlans	success	Synced 14 VLANs from OLT				\N	2025-12-22 11:11:02.293949
18	2	\N	command	success	Command executed		vlan 980 smart	vlan 980 smart\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:13:00.807014
19	2	\N	command	success	Command executed		vlan desc 980 description "Test VLAN 980"	vlan desc 980 description "Test VLAN 980"\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:13:14.828879
20	2	\N	create_vlan	success	VLAN 980 created		vlan 980 smart	vlan 980 smart\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:13:15.259574
50	2	\N	command	success	Command executed		btv\ntr069-server-config index 1 url http://102.205.236.243\nquit	btv\r\n\r\nMA5683T(config-btv)#tr069-server-configindex1urlhttp://102.205.236.243\r\n                    ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config-btv)#quit\r\n\r\nMA5683T(config)#	1	2025-12-22 12:30:35.822687
21	2	\N	command	success	Command executed		display board 0	display board 0\r\n  -------------------------------------------------------------------------\r\n  SlotID  BoardName  Status          SubType0 SubType1    Online/Offline\r\n  -------------------------------------------------------------------------\r\n  0       H807GPBD   Normal                           \r\n  1       H805GPFD   Normal                           \r\n  2     \r\n  3     \r\n  4     \r\n  5     \r\n  6       H802SCUN   Active_normal                    \r\n  7     \r\n  8       H801X2CS   Normal                           \r\n  9     \r\n  10    \r\n  11    \r\n  12    \r\n  -------------------------------------------------------------------------\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:16:17.865971
22	2	\N	command	success	Command executed		display port vlan 0/8/0	display port vlan 0/8/0\r\n  ---------------------------------------\r\n     1     15     16     33     34     36\r\n    69    200    302    660    903\r\n  ---------------------------------------\r\n  Total: 11\r\n  Native VLAN: 1\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:16:48.848532
23	2	\N	command	success	Command executed		port vlan 980 0/8	port vlan 980 0/8\r\n{ portlist<S><Length 1-255> }:\r\n\r\n  Command:\r\n          port vlan 980 0/8 \r\n                            ^\r\n  % Incomplete command, the error locates at '^'\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:17:03.864566
24	2	\N	add_vlan_uplink	success	VLAN 980 added to uplink 0/8/0		port vlan 980 0/8	port vlan 980 0/8\r\n{ portlist<S><Length 1-255> }:\r\n\r\n  Command:\r\n          port vlan 980 0/8 \r\n                            ^\r\n  % Incomplete command, the error locates at '^'\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:17:04.148492
25	2	\N	command	success	Command executed		port vlan 980 0/8 0	port vlan 980 0/8 0\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:17:52.895982
26	2	\N	add_vlan_uplink	success	VLAN 980 added to uplink 0/8/0		port vlan 980 0/8 0	port vlan 980 0/8 0\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:17:53.175638
27	2	\N	command	success	Command executed		display port vlan 0/8/0	display port vlan 0/8/0\r\n  ---------------------------------------\r\n     1     15     16     33     34     36\r\n    69    200    302    660    903    980\r\n  ---------------------------------------\r\n  Total: 12\r\n  Native VLAN: 1\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:18:07.887615
28	2	\N	command	success	Command executed		display board 0	display board 0\r\n  -------------------------------------------------------------------------\r\n  SlotID  BoardName  Status          SubType0 SubType1    Online/Offline\r\n  -------------------------------------------------------------------------\r\n  0       H807GPBD   Normal                           \r\n  1       H805GPFD   Normal                           \r\n  2     \r\n  3     \r\n  4     \r\n  5     \r\n  6       H802SCUN   Active_normal                    \r\n  7     \r\n  8       H801X2CS   Normal                           \r\n  9     \r\n  10    \r\n  11    \r\n  12    \r\n  -------------------------------------------------------------------------\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:24:53.912306
29	2	\N	command	success	Command executed		display ont info 0 0 all	display ont info 0 0 all\r\n  -----------------------------------------------------------------------------\r\n  F/S/P   ONT         SN         Control     Run      Config   Match    Protect\r\n          ID                     flag        state    state    state    side \r\n  -----------------------------------------------------------------------------\r\n  0/ 0/0    0  485754433D261F9B  active      offline  initial  initial  no \r\n  0/ 0/0    1  48575443F2D52CC3  active      online   normal   match    no \r\n  -----------------------------------------------------------------------------\r\n  F/S/P   ONT-ID   Description\r\n  -----------------------------------------------------------------------------\r\n  0/ 0/0       0   SNS000126_zone_Riverside_Kariobangi_descr_Qalabe_Qalicha\r\n                   _authd_20250606\r\n  0/ 0/0       1   SNS000154-01_zone_Riverside_Kariobangi_authd_20250715\r\n  -----------------------------------------------------------------------------\r\n  In port 0/ 0/0 , the total of ONTs are: 2, online: 1\r\n  -----------------------------------------------------------------------------\r\n  \r\n  -----------------------------------------------------------------------------\r\n  F/S/P   ONT         SN         Control     Run      Config   Match    Protect\r\n          ID                     flag        state    state    state    side \r\n  -----------------------------------------------------------------------------\r\n  0/ 0/2    0  48575443F2DB602B  active      online   failed   match    no \r\n  0/ 0/2    1  48575443F2D90B23  active      online   normal   match    no \r\n  0/ 0/2    2  48575443F2D90A4B  active      offline  initial  initial  no \r\n                                     \r\n\r\nMA5683T(config)#	\N	2025-12-22 11:26:06.250611
30	2	\N	command	success	Command executed		interface gpon 0/0\ndisplay ont optical-info 0 1\nquit	interface gpon 0/0\r\n\r\nMA5683T(config-if-gpon-0/0)#display ont optical-info 0 1\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1741Y5075490M   \r\n  Date Code                              : 17-11-21\r\n  Rx optical power(dBm)                  : -14.35\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.35\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]\r\n  Laser bias current(mA)                 : 7\r\n  Tx bias current warning threshold(mA)  : [-,-]\r\n  Tx bias current alarm threshold(mA)    : [2.000,100.000]\r\n  Temperature(C)                         : 45\r\n  Temperature warning threshold(C)       : [-,-]\r\n                                     \r\n\r\nMA5683T(config-if-gpon-0/0)#uit\r\n                            ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config-if-gpon-0/0)#	\N	2025-12-22 11:26:20.886628
39	2	\N	command	success	Command executed		interface gpon 0/1\r\nont add 0 sn-auth 48575443F2D53A8B omci ont-lineprofile-id 10 ont-srvprofile-id 10 desc "TEST_20251222"\r\nquit	interface gpon 0/1\r\n\r\nMA5683T(config-if-gpon-0/1)#ont add 0 sn-auth 48575443F2D53A8B omci ont-lineprof ile-id 10 ont-srvprofile-id 10 desc "TEST_20251222"\r\n  Failure: The line profile does not exist\r\n\r\nMA5683T(config-if-gpon-0/1)#quit\r\n\r\nMA5683T(config)#	1	2025-12-22 12:22:41.862583
31	2	\N	command	success	Command executed		interface gpon 0/0\ndisplay ont optical-info 2 0\nquit	interface gpon 0/0\r\n\r\nMA5683T(config-if-gpon-0/0)#display ont optical-info 2 0\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1717Y0805050B   \r\n  Date Code                              : 17-06-26\r\n  Rx optical power(dBm)                  : -24.09\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.35\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]\r\n  Laser bias current(mA)                 : 15\r\n  Tx bias current warning threshold(mA)  : [-,-]\r\n  Tx bias current alarm threshold(mA)    : [2.000,100.000]\r\n  Temperature(C)                         : 47\r\n  Temperature warning threshold(C)       : [-,-]\r\n                                     \r\n\r\nMA5683T(config-if-gpon-0/0)#uit\r\n                            ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config-if-gpon-0/0)#	\N	2025-12-22 11:26:34.824384
32	2	\N	command	success	Command executed		display board 0	display board 0\r\n  -------------------------------------------------------------------------\r\n  SlotID  BoardName  Status          SubType0 SubType1    Online/Offline\r\n  -------------------------------------------------------------------------\r\n  0       H807GPBD   Normal                           \r\n  1       H805GPFD   Normal                           \r\n  2     \r\n  3     \r\n  4     \r\n  5     \r\n  6       H802SCUN   Active_normal                    \r\n  7     \r\n  8       H801X2CS   Normal                           \r\n  9     \r\n  10    \r\n  11    \r\n  12    \r\n  -------------------------------------------------------------------------\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:27:59.806524
33	2	\N	command	success	Command executed		display ont info 0 0 all	display ont info 0 0 all\r\n  -----------------------------------------------------------------------------\r\n  F/S/P   ONT         SN         Control     Run      Config   Match    Protect\r\n          ID                     flag        state    state    state    side \r\n  -----------------------------------------------------------------------------\r\n  0/ 0/0    0  485754433D261F9B  active      offline  initial  initial  no \r\n  0/ 0/0    1  48575443F2D52CC3  active      online   normal   match    no \r\n  -----------------------------------------------------------------------------\r\n  F/S/P   ONT-ID   Description\r\n  -----------------------------------------------------------------------------\r\n  0/ 0/0       0   SNS000126_zone_Riverside_Kariobangi_descr_Qalabe_Qalicha\r\n                   _authd_20250606\r\n  0/ 0/0       1   SNS000154-01_zone_Riverside_Kariobangi_authd_20250715\r\n  -----------------------------------------------------------------------------\r\n  In port 0/ 0/0 , the total of ONTs are: 2, online: 1\r\n  -----------------------------------------------------------------------------\r\n  \r\n  -----------------------------------------------------------------------------\r\n  F/S/P   ONT         SN         Control     Run      Config   Match    Protect\r\n          ID                     flag        state    state    state    side \r\n  -----------------------------------------------------------------------------\r\n  0/ 0/2    0  48575443F2DB602B  active      online   failed   match    no \r\n  0/ 0/2    1  48575443F2D90B23  active      online   normal   match    no \r\n  0/ 0/2    2  48575443F2D90A4B  active      offline  initial  initial  no \r\n                                     \r\n\r\nMA5683T(config)#	\N	2025-12-22 11:29:12.14781
34	2	\N	command	success	Command executed		display board 0	display board 0\r\n  -------------------------------------------------------------------------\r\n  SlotID  BoardName  Status          SubType0 SubType1    Online/Offline\r\n  -------------------------------------------------------------------------\r\n  0       H807GPBD   Normal                           \r\n  1       H805GPFD   Normal                           \r\n  2     \r\n  3     \r\n  4     \r\n  5     \r\n  6       H802SCUN   Active_normal                    \r\n  7     \r\n  8       H801X2CS   Normal                           \r\n  9     \r\n  10    \r\n  11    \r\n  12    \r\n  -------------------------------------------------------------------------\r\n\r\nMA5683T(config)#	\N	2025-12-22 11:30:06.881342
35	2	\N	command	success	Command executed		display ont info 0 0 all	display ont info 0 0 all\r\n  -----------------------------------------------------------------------------\r\n  F/S/P   ONT         SN         Control     Run      Config   Match    Protect\r\n          ID                     flag        state    state    state    side \r\n  -----------------------------------------------------------------------------\r\n  0/ 0/0    0  485754433D261F9B  active      offline  initial  initial  no \r\n  0/ 0/0    1  48575443F2D52CC3  active      online   normal   match    no \r\n  -----------------------------------------------------------------------------\r\n  F/S/P   ONT-ID   Description\r\n  -----------------------------------------------------------------------------\r\n  0/ 0/0       0   SNS000126_zone_Riverside_Kariobangi_descr_Qalabe_Qalicha\r\n                   _authd_20250606\r\n  0/ 0/0       1   SNS000154-01_zone_Riverside_Kariobangi_authd_20250715\r\n  -----------------------------------------------------------------------------\r\n  In port 0/ 0/0 , the total of ONTs are: 2, online: 1\r\n  -----------------------------------------------------------------------------\r\n  \r\n  -----------------------------------------------------------------------------\r\n  F/S/P   ONT         SN         Control     Run      Config   Match    Protect\r\n          ID                     flag        state    state    state    side \r\n  -----------------------------------------------------------------------------\r\n  0/ 0/2    0  48575443F2DB602B  active      online   failed   match    no \r\n  0/ 0/2    1  48575443F2D90B23  active      online   normal   match    no \r\n  0/ 0/2    2  48575443F2D90A4B  active      offline  initial  initial  no \r\n                                     \r\n\r\nMA5683T(config)#	\N	2025-12-22 11:31:19.239401
36	1	\N	command	failed	Connection failed: Connection timed out		display ont autofind all		\N	2025-12-22 12:19:40.474965
37	2	\N	command	success	Command executed		display ont info summary	display ont info summary\r\n                                 ^\r\n  % Parameter error, the error locates at '^'\r\n\r\nMA5683T(config)#	\N	2025-12-22 12:20:15.882791
38	2	\N	command	success	Command executed		display ont autofind all	display ont autofind all\r\n   ----------------------------------------------------------------------------\r\n   Number              : 1\r\n   F/S/P               : 0/1/0\r\n   Ont SN              : 48575443F2D53A8B (HWTC-F2D53A8B)\r\n   Password            : 0x00000000000000000000\r\n   Loid                : \r\n   Checkcode           : \r\n   VendorID            : HWTC\r\n   Ont Version         : 10C7.A\r\n   Ont SoftwareVersion : V5R019C10S125\r\n   Ont EquipmentID     : HG8546M\r\n   Ont autofind time   : 2025-12-20 11:37:38+03:00\r\n   ----------------------------------------------------------------------------\r\n   The number of GPON autofind ONT is 1\r\n\r\nMA5683T(config)#	\N	2025-12-22 12:20:58.863671
41	2	\N	command	success	Command executed		display ont-lineprofile gpon all	display ont-lineprofile gpon all\r\n  -----------------------------------------------------------------------------\r\n  Profile-ID  Profile-name                                Binding times\r\n  -----------------------------------------------------------------------------\r\n  0           line-profile_default_0                      0            \r\n  1           one-isp-lp                                  0            \r\n  2           SMARTOLT_FLEXIBLE_GPON                      554          \r\n  4           Generic_1_V902                              1            \r\n  -----------------------------------------------------------------------------\r\n  Total: 4\r\n\r\nMA5683T(config)#	\N	2025-12-22 12:23:13.827385
42	2	\N	command	success	Command executed		display ont-srvprofile gpon all	display ont-srvprofile gpon all\r\n  -----------------------------------------------------------------------------\r\n  Profile-ID  Profile-name                                Binding times\r\n  -----------------------------------------------------------------------------\r\n  0           srv-profile_default_0                       0            \r\n  1           one-isp-srv-profile                         0            \r\n  2           HG8247                                      15           \r\n  3           HG8546M                                     461          \r\n  4           HG8346M                                     1            \r\n  5           HG8145V5                                    47           \r\n  6           HG8145V                                     8            \r\n  7           HG8447                                      4            \r\n  8           ONU-type-eth-4-pots-2-catv-0                0            \r\n  9           HG8245H                                     6            \r\n  10          HG6143D                                     2            \r\n  11          HS8145V                                     3            \r\n  12          HS8546V                                     1            \r\n  13          ZTE-F660V5.2                                0            \r\n  14          HG865                                       1            \r\n  15          HG8545M                                     0            \r\n  16          AHD1704NU                                   1            \r\n  17          TT919G                                      0            \r\n  18          HG3                                         0            \r\n  19          IGD                                         2            \r\n                                     \r\n\r\nMA5683T(config)#	\N	2025-12-22 12:24:26.17404
43	2	\N	command	success	Command executed		interface gpon 0/1\r\nont add 0 sn-auth 48575443F2D53A8B omci ont-lineprofile-id 2 ont-srvprofile-id 3 desc "TEST_AUTH_20251222"\r\nquit	interface gpon 0/1\r\n\r\nMA5683T(config-if-gpon-0/1)#ont add 0 sn-auth 48575443F2D53A8B omci ont-lineprof ile-id 2 ont-srvprofile-id 3 desc "TEST_AUTH_20251222"\r\n  Number of ONTs that can be added: 1, success: 1\r\n  PortID :0, ONTID :0\r\n\r\nMA5683T(config-if-gpon-0/1)#quit\r\n\r\nMA5683T(config)#	1	2025-12-22 12:25:09.856813
45	2	\N	command	success	Command executed		\nont-lineprofile gpon profile-id 100 profile-name "CRM-Standard-LP"\ntcont 1 dba-profile-id 8\ngem add 1 eth tcont 1\ngem mapping 1 0 vlan 100\ntr069-server-config ip-address 102.205.236.243\ncommit\nquit\n	\r\n\r\nMA5683T(config)#ont-lineprofile gpon profile-id 100 profile-name "CRM-Standard-L P"\r\n\r\nMA5683T(config-gpon-lineprofile-100)#tcont 1 dba-profile-id 8\r\n\r\nMA5683T(config-gpon-lineprofile-100)#gem add 1 eth tcont 1\r\n{ <cr>|cascade<K>|downstream-priority-queue<K>|encrypt<K>|gem-car<K>|priority-queue<K> }:gem-car mapping10vlan100\r\n\r\n  Command:\r\n          gem add 1 eth tcont 1 gem-car mapping10vlan100\r\n                                        ^\r\n  % Parameter error, the error locates at '^'\r\n\r\nMA5683T(config-gpon-lineprofile-100)#tr069-server-configip-address102.205.236.24 3\r\n                                     ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config-gpon-lineprofile-100)#commit\r\n\r\nMA5683T(config-gpon-lineprofile-100)#quit\r\n\r\nMA5683T(config)#\r\n\r\nMA5683T(config)#	1	2025-12-22 12:27:51.035683
46	2	\N	command	success	Command executed		\nont-srvprofile gpon profile-id 100 profile-name "CRM-Standard-SRV"\nont-port eth adaptive pots adaptive\nport vlan eth 1 100\ncommit\nquit\n	\r\n\r\nMA5683T(config)#ont-srvprofile gpon profile-id 100 profile-name "CRM-Standard-SR V"\r\n\r\nMA5683T(config-gpon-srvprofile-100)#ont-port eth adaptive pots adaptive\r\n{ <cr>|catv<K>|moca<K>|tdm-srvtype<K>|tdm-type<K>|tdm<K>|vdsl<K> }:portvlaneth11 00\r\n\r\n  Command:\r\n          ont-port eth adaptive pots adaptive portvlaneth1100\r\n                                              ^\r\n  % Too many parameters, the error locates at '^'\r\n\r\nMA5683T(config-gpon-srvprofile-100)#commit\r\n\r\nMA5683T(config-gpon-srvprofile-100)#quit\r\n\r\nMA5683T(config)#\r\n\r\nMA5683T(config)#	1	2025-12-22 12:28:04.896729
47	2	\N	command	success	Command executed		display ont-lineprofile gpon profile-id 100	display ont-lineprofile gpon profile-id 100\r\n  -----------------------------------------------------------------------------\r\n  Profile-ID          :100\r\n  Profile-name        :CRM-Standard-LP\r\n  Access-type         :GPON\r\n  -----------------------------------------------------------------------------\r\n  FEC upstream switch :Disable\r\n  OMCC encrypt switch :Off\r\n  Qos mode            :PQ\r\n  Mapping mode        :VLAN\r\n  TR069 management    :Disable\r\n  TR069 IP index      :0\r\n  -----------------------------------------------------------------------------\r\n  <T-CONT   0>          DBA Profile-ID:1\r\n  <T-CONT   1>          DBA Profile-ID:8\r\n  -----------------------------------------------------------------------------\r\n  Binding times       :0\r\n  -----------------------------------------------------------------------------\r\n\r\nMA5683T(config)#	1	2025-12-22 12:28:18.831702
48	2	\N	command	success	Command executed		ont-lineprofile gpon profile-id 100\ngem add 1 eth tcont 1\ngem mapping 1 0 vlan 100\ncommit\nquit	ont-lineprofile gpon profile-id 100\r\n{ <cr>|profile-name<K> }:gemadd1ethtcont1\r\n\r\n  Command:\r\n          ont-lineprofile gpon profile-id 100 gemadd1ethtcont1\r\n                                              ^\r\n  % Too many parameters, the error locates at '^'\r\n\r\nMA5683T(config)#gemmapping10vlan100\r\n                ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config)#commit\r\n                ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config)#quit\r\n\r\nMA5683T#	1	2025-12-22 12:30:07.82354
49	2	\N	command	success	Command executed		ont-srvprofile gpon profile-id 100\nont-port eth adaptive pots adaptive\ncommit\nquit	ont-srvprofile gpon profile-id 100\r\n{ <cr>|profile-name<K> }:ont-portethadaptivepotsadaptive\r\n\r\n  Command:\r\n          ont-srvprofile gpon profile-id 100 ont-portethadaptivepotsadaptive\r\n                                             ^\r\n  % Too many parameters, the error locates at '^'\r\n\r\nMA5683T(config)#commit\r\n                ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config)#quit\r\n\r\nMA5683T#	1	2025-12-22 12:30:21.882279
51	2	\N	command	success	Command executed		ont-lineprofile gpon profile-id 100\ntr069-management enable\ntr069-server-config ip-index 1\ncommit\nquit	ont-lineprofile gpon profile-id 100\r\n{ <cr>|profile-name<K> }:tr069-managementenable\r\n\r\n  Command:\r\n          ont-lineprofile gpon profile-id 100 tr069-managementenable\r\n                                              ^\r\n  % Too many parameters, the error locates at '^'\r\n\r\nMA5683T(config)#tr069-server-configip-index1\r\n                ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config)#commit\r\n                ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config)#quit\r\n\r\nMA5683T#	1	2025-12-22 12:30:49.862239
52	2	\N	command	success	Command executed		display ont-lineprofile gpon profile-id 100	display ont-lineprofile gpon profile-id 100\r\n  -----------------------------------------------------------------------------\r\n  Profile-ID          :100\r\n  Profile-name        :CRM-Standard-LP\r\n  Access-type         :GPON\r\n  -----------------------------------------------------------------------------\r\n  FEC upstream switch :Disable\r\n  OMCC encrypt switch :Off\r\n  Qos mode            :PQ\r\n  Mapping mode        :VLAN\r\n  TR069 management    :Disable\r\n  TR069 IP index      :0\r\n  -----------------------------------------------------------------------------\r\n  <T-CONT   0>          DBA Profile-ID:1\r\n  <T-CONT   1>          DBA Profile-ID:8\r\n  -----------------------------------------------------------------------------\r\n  Binding times       :0\r\n  -----------------------------------------------------------------------------\r\n\r\nMA5683T(config)#	1	2025-12-22 12:31:03.901166
53	2	\N	command	success	Command executed		display ont-srvprofile gpon profile-id 100	display ont-srvprofile gpon profile-id 100\r\n  -----------------------------------------------------------------------------\r\n  Profile-ID  : 100\r\n  Profile-name: CRM-Standard-SRV\r\n  Access-type : GPON\r\n  -----------------------------------------------------------------------------\r\n  Port-type     Port-number\r\n  -----------------------------------------------------------------------------\r\n  POTS          0\r\n  ETH           0\r\n  VDSL          0\r\n  TDM           0\r\n  MOCA          0\r\n  CATV          0\r\n  -----------------------------------------------------------------------------\r\n  TDM port type                     : E1\r\n  TDM service type                  : TDMoGem\r\n  MAC learning function switch      : Enable\r\n  ONT transparent function switch   : Disable\r\n  Ring check switch                 : Disable\r\n  Ring port auto-shutdown           : Enable\r\n  Ring detect frequency             : 8 (pps)\r\n  Ring resume interval              : 300 (s)\r\n  Multicast forward mode            : Unconcern\r\n                                     \r\n\r\nMA5683T(config)#	1	2025-12-22 12:32:16.112512
54	2	\N	command	success	Command executed		ont tr069-server-profile add profile-id 1 url http://102.205.236.243	ont tr069-server-profile add profile-id 1 url http://102.205.236 .243\r\n{ <cr>|user<K> }:\r\n\r\n  Command:\r\n          ont tr069-server-profile add profile-id 1 url http://102.205.236.243 \r\n  Failure: The ONT TR069 server profile already exists\r\n\r\nMA5683T(config)#	1	2025-12-22 12:33:57.901097
55	2	\N	command	success	Command executed		ont-lineprofile gpon profile-id 100	ont-lineprofile gpon profile-id 100\r\n{ <cr>|profile-name<K> }:\r\n\r\n  Command:\r\n          ont-lineprofile gpon profile-id 100 \r\n\r\nMA5683T(config-gpon-lineprofile-100)#	1	2025-12-22 12:34:41.864352
56	2	\N	command	success	Command executed		gem add 1 eth tcont 1	gemadd1ethtcont1\r\n                ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config)#	1	2025-12-22 12:34:55.886514
57	2	\N	command	success	Command executed		gem add 2 eth tcont 1	gemadd2ethtcont1\r\n                ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config)#	1	2025-12-22 12:35:09.810967
58	2	\N	command	success	Command executed		gem mapping 1 0 vlan 100	gemmapping10vlan100\r\n                ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config)#	1	2025-12-22 12:35:23.83759
59	2	\N	command	success	Command executed		gem mapping 2 1 vlan 69	gemmapping21vlan69\r\n                ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config)#	1	2025-12-22 12:35:37.864583
60	2	\N	command	success	Command executed		tr069-management enable	tr069-managementenable\r\n                ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config)#	1	2025-12-22 12:35:51.88843
61	2	\N	command	success	Command executed		commit	commit\r\n                ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config)#	1	2025-12-22 12:36:05.815089
62	2	\N	command	success	Command executed		quit	quit\r\n\r\nMA5683T#	1	2025-12-22 12:36:19.845561
63	2	\N	command	success	Command executed		display ont optical-info 0/1 0 0	display ont optical-info0/100\r\n                            ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config)#	1	2025-12-22 13:58:07.866622
64	2	\N	command	success	Command executed		display ont info 0/1 0 0	display ont info 0/100\r\n                                 ^\r\n  % Parameter error, the error locates at '^'\r\n\r\nMA5683T(config)#	1	2025-12-22 13:58:21.889382
65	2	\N	command	success	Command executed		display ont optical-info 0/1 0 0	display ont optical-info0/100\r\n                            ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config)#	1	2025-12-22 13:59:35.87041
66	2	\N	command	success	Command executed		display ont info 0/1 0 0	display ont info 0/100\r\n                                 ^\r\n  % Parameter error, the error locates at '^'\r\n\r\nMA5683T(config)#	1	2025-12-22 13:59:49.871448
67	2	\N	command	success	Command executed		display ont optical-info 0/1 0 0	display ont optical-info0/100\r\n                            ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config)#	1	2025-12-22 14:01:46.810245
68	2	\N	command	success	Command executed		display ont info 0/1 0 0	display ont info 0/100\r\n                                 ^\r\n  % Parameter error, the error locates at '^'\r\n\r\nMA5683T(config)#	1	2025-12-22 14:02:00.823969
69	2	\N	command	success	Command executed		display ont optical-info 0/1/0 0	display ont optical-info0/1/00\r\n                            ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config)#	1	2025-12-22 14:07:49.83906
70	2	\N	command	success	Command executed		display ont info 0/1/0 0	display ont info 0/1/00\r\n                                 ^\r\n  % Parameter error, the error locates at '^'\r\n\r\nMA5683T(config)#	1	2025-12-22 14:08:03.840416
71	1	\N	command	failed	Connection failed: Connection timed out		display ont optical-info 0/1/0 0		\N	2025-12-22 14:10:52.772735
72	1	\N	command	failed	Connection failed: Connection timed out		display ont optical-info 0/1/0 0		\N	2025-12-22 14:11:20.547186
73	2	\N	command	success	Command executed		display ont optical-info 0/1/0 0	display ont optical-info0/1/00\r\n                            ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config)#	\N	2025-12-22 14:12:36.840601
74	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont optical-info 0 0	display ont optical-info 0 0\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1819WB916773P   \r\n  Date Code                              : 18-06-27\r\n  Rx optical power(dBm)                  : -0.65\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.24\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]\r\n  Laser bias current(mA)                 : 15\r\n  Tx bias current warning threshold(mA)  : [-,-]\r\n  Tx bias current alarm threshold(mA)    : [2.000,100.000]\r\n  Temperature(C)                         : 62\r\n  Temperature warning threshold(C)       : [-,-]\r\n                                     \r\n\r\nMA5683T(config-if-gpon-0/1)#	1	2025-12-22 14:23:19.90561
75	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont info 0 0	display ont info 0 0\r\n  -----------------------------------------------------------------------------\r\n  F/S/P                   : 0/1/0\r\n  ONT-ID                  : 0\r\n  Control flag            : active\r\n  Run state               : online\r\n  Config state            : normal\r\n  Match state             : match\r\n  DBA type                : SR\r\n  ONT distance(m)         : 10\r\n  ONT battery state       : not support\r\n  Memory occupation       : 43%\r\n  CPU occupation          : 1%\r\n  Temperature             : 69(C)\r\n  Authentic type          : SN-auth\r\n  SN                      : 48575443F2D53A8B (HWTC-F2D53A8B)\r\n  Management mode         : OMCI\r\n  Software work mode      : normal\r\n  Isolation state         : normal\r\n  ONT IP 0 address/mask   : -\r\n  Description             : TEST_AUTH_20251222\r\n  Last down cause         : dying-gasp\r\n  Last up time            : 2025-12-22 15:41:34+03:00\r\n  Last down time          : 2025-12-22 15:40:27+03:00\r\n                                     \r\n\r\nMA5683T(config-if-gpon-0/1)#	1	2025-12-22 14:24:32.878688
76	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont optical-info 0 0	display ont optical-info 0 0\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1819WB916773P   \r\n  Date Code                              : 18-06-27\r\n  Rx optical power(dBm)                  : -0.65\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.34\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]\r\n  Laser bias current(mA)                 : 15\r\n  Tx bias current warning threshold(mA)  : [-,-]\r\n  Tx bias current alarm threshold(mA)    : [2.000,100.000]\r\n  Temperature(C)                         : 62\r\n  Temperature warning threshold(C)       : [-,-]\r\n                                     \r\n\r\nMA5683T(config-if-gpon-0/1)#	1	2025-12-22 14:25:52.885256
77	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont info 0 0	display ont info 0 0\r\n  -----------------------------------------------------------------------------\r\n  F/S/P                   : 0/1/0\r\n  ONT-ID                  : 0\r\n  Control flag            : active\r\n  Run state               : online\r\n  Config state            : normal\r\n  Match state             : match\r\n  DBA type                : SR\r\n  ONT distance(m)         : 10\r\n  ONT battery state       : not support\r\n  Memory occupation       : 43%\r\n  CPU occupation          : 1%\r\n  Temperature             : 69(C)\r\n  Authentic type          : SN-auth\r\n  SN                      : 48575443F2D53A8B (HWTC-F2D53A8B)\r\n  Management mode         : OMCI\r\n  Software work mode      : normal\r\n  Isolation state         : normal\r\n  ONT IP 0 address/mask   : -\r\n  Description             : TEST_AUTH_20251222\r\n  Last down cause         : dying-gasp\r\n  Last up time            : 2025-12-22 15:41:34+03:00\r\n  Last down time          : 2025-12-22 15:40:27+03:00\r\n                                     \r\n\r\nMA5683T(config-if-gpon-0/1)#	1	2025-12-22 14:27:05.948958
78	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont optical-info 0 0\r\ndisplay ont info 0 0	                                       Temperature alarm threshold(C)         : [-61,95]\r\n  Voltage(V)                             : 3.280\r\n  Supply voltage warning threshold(V)    : [-,-]\r\n  Supply voltage alarm threshold(V)      : [3.000,3.600]\r\n  OLT Rx ONT optical power(dBm)          : -50.00, out of range[-35.00, -15.00]\r\n  CATV Rx optical power(dBm)             : -\r\n  CATV Rx power alarm threshold(dBm)     : [-,-]\r\n  -----------------------------------------------------------------------------\r\n\r\nMA5683T(config-if-gpon-0/1)#isplayontinfo00\r\n                            ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config-if-gpon-0/1)#	\N	2025-12-22 14:29:58.858763
79	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont optical-info 0 0\r\ndisplay ont info 0 0	                                       Temperature alarm threshold(C)         : [-61,95]\r\n  Voltage(V)                             : 3.280\r\n  Supply voltage warning threshold(V)    : [-,-]\r\n  Supply voltage alarm threshold(V)      : [3.000,3.600]\r\n  OLT Rx ONT optical power(dBm)          : -50.00, out of range[-35.00, -15.00]\r\n  CATV Rx optical power(dBm)             : -\r\n  CATV Rx power alarm threshold(dBm)     : [-,-]\r\n  -----------------------------------------------------------------------------\r\n\r\nMA5683T(config-if-gpon-0/1)#isplayontinfo00\r\n                            ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config-if-gpon-0/1)#	\N	2025-12-22 14:31:34.8823
80	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont optical-info 0 0	display ont optical-info 0 0\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1819WB916773P   \r\n  Date Code                              : 18-06-27\r\n  Rx optical power(dBm)                  : -0.68\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.30\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]\r\n  Laser bias current(mA)                 : 14\r\n  Tx bias current warning threshold(mA)  : [-,-]\r\n  Tx bias current alarm threshold(mA)    : [2.000,100.000]\r\n  Temperature(C)                         : 61\r\n  Temperature warning threshold(C)       : [-,-]\r\n	\N	2025-12-22 14:36:52.553473
81	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont optical-info 0 0	display ont optical-info 0 0\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1819WB916773P   \r\n  Date Code                              : 18-06-27\r\n  Rx optical power(dBm)                  : -0.67\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.17\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]\r\n  Laser bias current(mA)                 : 15\r\n  Tx bias current warning threshold(mA)  : [-,-]\r\n  Tx bias current alarm threshold(mA)    : [2.000,100.000]\r\n  Temperature(C)                         : 61\r\n  Temperature warning threshold(C)       : [-,-]\r\n	\N	2025-12-22 14:38:14.523419
82	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont optical-info 0 0	display ont optical-info 0 0\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1819WB916773P   \r\n  Date Code                              : 18-06-27\r\n  Rx optical power(dBm)                  : -0.67\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.23\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]\r\n  Laser bias current(mA)                 : 15\r\n  Tx bias current warning threshold(mA)  : [-,-]\r\n  Tx bias current alarm threshold(mA)    : [2.000,100.000]\r\n  Temperature(C)                         : 61\r\n  Temperature warning threshold(C)       : [-,-]\r\n	\N	2025-12-22 14:38:57.548353
83	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont optical-info 0 0	display ont optical-info 0 0\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1819WB916773P   \r\n  Date Code                              : 18-06-27\r\n  Rx optical power(dBm)                  : -0.68\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.35\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]\r\n  Laser bias current(mA)                 : 14\r\n  Tx bias current warning threshold(mA)  : [-,-]\r\n  Tx bias current alarm threshold(mA)    : [2.000,100.000]\r\n  Temperature(C)                         : 61\r\n  Temperature warning threshold(C)       : [-,-]\r\n	1	2025-12-22 14:44:09.534228
84	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont optical-info 0 0	display ont optical-info 0 0\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1819WB916773P   \r\n  Date Code                              : 18-06-27\r\n  Rx optical power(dBm)                  : -0.68\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.24\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]\r\n  Laser bias current(mA)                 : 14\r\n  Tx bias current warning threshold(mA)  : [-,-]\r\n  Tx bias current alarm threshold(mA)    : [2.000,100.000]\r\n  Temperature(C)                         : 61\r\n  Temperature warning threshold(C)       : [-,-]\r\n	1	2025-12-22 14:44:36.524358
85	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont optical-info 0 0	display ont optical-info 0 0\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1819WB916773P   \r\n  Date Code                              : 18-06-27\r\n  Rx optical power(dBm)                  : -0.67\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.35\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]\r\n  Laser bias current(mA)                 : 14\r\n  Tx bias current warning threshold(mA)  : [-,-]\r\n  Tx bias current alarm threshold(mA)    : [2.000,100.000]\r\n  Temperature(C)                         : 61\r\n  Temperature warning threshold(C)       : [-,-]\r\n	1	2025-12-22 14:45:03.523498
86	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont optical-info 0 0	display ont optical-info 0 0\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1819WB916773P   \r\n  Date Code                              : 18-06-27\r\n  Rx optical power(dBm)                  : -0.67\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.22\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]\r\n  Laser bias current(mA)                 : 14\r\n  Tx bias current warning threshold(mA)  : [-,-]\r\n  Tx bias current alarm threshold(mA)    : [2.000,100.000]\r\n  Temperature(C)                         : 61\r\n  Temperature warning threshold(C)       : [-,-]\r\n	1	2025-12-22 14:45:31.549423
87	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont optical-info 0 0	display ont optical-info 0 0\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1819WB916773P   \r\n  Date Code                              : 18-06-27\r\n  Rx optical power(dBm)                  : -0.65\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.19\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]\r\n  Laser bias current(mA)                 : 14\r\n  Tx bias current warning threshold(mA)  : [-,-]\r\n  Tx bias current alarm threshold(mA)    : [2.000,100.000]\r\n  Temperature(C)                         : 61\r\n  Temperature warning threshold(C)       : [-,-]\r\n	1	2025-12-22 14:46:00.557352
88	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont optical-info 0 0	display ont optical-info 0 0\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1819WB916773P   \r\n  Date Code                              : 18-06-27\r\n  Rx optical power(dBm)                  : -0.68\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.25\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]\r\n  Laser bias current(mA)                 : 14\r\n  Tx bias current warning threshold(mA)  : [-,-]\r\n  Tx bias current alarm threshold(mA)    : [2.000,100.000]\r\n  Temperature(C)                         : 61\r\n  Temperature warning threshold(C)       : [-,-]\r\n	1	2025-12-22 14:46:24.563501
89	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont optical-info 0 0	display ont optical-info 0 0\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1819WB916773P   \r\n  Date Code                              : 18-06-27\r\n  Rx optical power(dBm)                  : -0.67\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.31\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]\r\n  Laser bias current(mA)                 : 14\r\n  Tx bias current warning threshold(mA)  : [-,-]\r\n  Tx bias current alarm threshold(mA)    : [2.000,100.000]\r\n  Temperature(C)                         : 61\r\n  Temperature warning threshold(C)       : [-,-]\r\n	1	2025-12-22 14:47:02.567381
90	1	\N	command	failed	Connection failed: Connection timed out		interface gpon 0/1\r\ndisplay ont optical-info 0 1\r\nquit\r\ndisplay ont info 0 1 0 1		\N	2025-12-22 14:50:06.032041
91	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont optical-info 0 0\r\nquit\r\ndisplay ont info 0 1 0 0	display ont info 0 1 00\r\n                                                 ^\r\n  % Too many parameters, the error locates at '^'\r\n\r\nMA5683T(config-if-gpon-0/1)#	\N	2025-12-22 14:50:39.871673
92	1	\N	command	failed	Connection failed: Connection timed out		interface gpon 0/1\r\ndisplay ont optical-info 0 0\r\nquit\r\ndisplay ont info 0/1/0 0		\N	2025-12-22 14:52:08.48916
93	1	\N	command	failed	Connection failed: Connection timed out		display ont info 0/1/0 0		\N	2025-12-22 14:52:35.849823
94	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont optical-info 0 0	display ont optical-info 0 0\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1819WB916773P   \r\n  Date Code                              : 18-06-27\r\n  Rx optical power(dBm)                  : -0.69\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.17\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]\r\n  Laser bias current(mA)                 : 14\r\n  Tx bias current warning threshold(mA)  : [-,-]\r\n  Tx bias current alarm threshold(mA)    : [2.000,100.000]\r\n  Temperature(C)                         : 60\r\n  Temperature warning threshold(C)       : [-,-]\r\n	\N	2025-12-22 14:54:20.533336
95	2	\N	command	success	Command executed		display ont info 0/1/0 0	display ont info 0/1/00\r\n                                 ^\r\n  % Parameter error, the error locates at '^'\r\n\r\nMA5683T(config)#	\N	2025-12-22 14:54:33.864402
96	1	\N	command	failed	Connection failed: Connection timed out		display ont info 0/1/0 0		\N	2025-12-22 14:55:05.695489
97	1	\N	command	failed	Connection failed: Connection timed out		display ont info 0/1/0 0		\N	2025-12-22 14:55:35.383625
98	1	\N	command	failed	Connection failed: Connection timed out		interface gpon 0/1\r\ndisplay ont optical-info 0 0\r\nquit\r\ndisplay ont info 0/1/0 0		\N	2025-12-22 14:57:11.140399
99	1	\N	command	failed	Connection failed: Connection timed out		interface gpon 0/1\r\ndisplay ont optical-info 0 0\r\nquit\r\ndisplay ont info 0/1/0 0		\N	2025-12-22 14:57:37.276118
100	1	\N	command	failed	Connection failed: Connection timed out		interface gpon 0/1\r\ndisplay ont optical-info 0 0		\N	2025-12-22 14:58:08.573463
101	2	\N	command	success	Command executed		interface gpon 0/1\r\ndisplay ont optical-info 0 0	display ont optical-info 0 0\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1819WB916773P   \r\n  Date Code                              : 18-06-27\r\n  Rx optical power(dBm)                  : -0.70\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.32\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]\r\n  Laser bias current(mA)                 : 14\r\n  Tx bias current warning threshold(mA)  : [-,-]\r\n  Tx bias current alarm threshold(mA)    : [2.000,100.000]\r\n  Temperature(C)                         : 60\r\n  Temperature warning threshold(C)       : [-,-]\r\n	\N	2025-12-22 15:00:17.520378
102	2	\N	command_via_service	failed	Service connection failed: Operation timed out after 60002 milliseconds with 0 bytes received		interface gpon 0/1\r\ndisplay ont optical-info 0 0		1	2025-12-23 01:16:36.6984
103	2	\N	command_via_service	failed	Service connection failed: Operation timed out after 60001 milliseconds with 0 bytes received		interface gpon 0/1\r\ndisplay ont optical-info 0 0		1	2025-12-23 01:17:37.837771
104	2	\N	command_via_service	failed	Service connection failed: Operation timed out after 60002 milliseconds with 0 bytes received		interface gpon 0/1\r\ndisplay ont optical-info 0 0		1	2025-12-23 01:18:46.767851
105	2	\N	command_via_service	failed	Service connection failed: Operation timed out after 60002 milliseconds with 0 bytes received		interface gpon 0/1\r\ndisplay ont optical-info 0 0		1	2025-12-23 01:19:47.920785
106	2	\N	command_via_service	success	Command executed via persistent session		interface gpon 0/1\r\nont add 0 sn-auth 48575443F2D53A8B omci ont-lineprofile-id 2 ont-srvprofile-id 3 desc "TestONU_HG8546M"\r\nquit	interface gpon 0/1\r\n\r\nMA5683T(config-if-gpon-0/1)#ont add 0 sn-auth 48575443F2D53A8B omci ont-lineprof [1Dile-id 2 ont-srvprofile-id 3 desc "TestONU_HG8546M"\r\n  Number of ONTs that can be added: 1, success: 1\r\n  PortID :0, ONTID :0\r\n\r\nMA5683T(config-if-gpon-0/1)#quit\r\n\r\nMA5683T(config)#\r\n\r\nMA5683T(config)#	1	2025-12-24 07:51:08.46985
107	2	\N	command_via_service	failed	Service connection failed: Operation timed out after 60001 milliseconds with 0 bytes received		service-port vlan 902 gpon 0/1/0 ont 0 gemport 1 multi-service user-vlan rx-cttr 6 tx-cttr 6		1	2025-12-24 07:52:09.435463
108	2	\N	command_via_service	success	Command executed via persistent session		interface gpon 0/1\r\nont port native-vlan 0 0 eth 1 vlan 69 priority 0\r\nont ipconfig 0 0 ip-index 0 dhcp vlan 69\r\nquit	interface gpon 0/1\r\n\r\nMA5683T(config-if-gpon-0/1)#ont port native-vlan 0 0 eth 1 vlan 69 priority 0\r\n\r\nMA5683T(config-if-gpon-0/1)#ont ipconfig 0 0 ip-index 0 dhcp vlan 69\r\n{ <cr>|priority<K>	1	2025-12-24 07:52:40.29492
109	2	\N	command_via_service	success	Command executed via persistent session		service-port vlan 69 gpon 0/1/0 ont 0 gemport 2 multi-service user-vlan rx-cttr 6 tx-cttr 6	service-portvlan69gpon0/1/0ont0gemport2multi-service [1Duser-vlanrx-cttr6tx-cttr6\r\n                            ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config-if-gpon-0/1)#	1	2025-12-24 07:53:11.010275
111	2	\N	command_via_service	success	Command executed via persistent session		interface gpon 0/1\r\ndisplay ont optical-info 0 0	interface gpon 0/1\r\n\r\nMA5683T(config-if-gpon-0/1)#display ont optical-info 0 0\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1819WB916773P   \r\n  Date Code                              : 18-06-27\r\n  Rx optical power(dBm)                  : -0.69\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.35\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]\r\n  Laser bias current(mA)                 : 15\r\n  Tx bias current warning threshold(mA)  : [-,-]\r\n  Tx bias current alarm threshold(mA)    : [2.000,100.000]\r\n  Temperature(C)                         : 64\r\n  Temperature warning threshold(C)       : [-,-]\r\n	1	2025-12-24 10:21:14.639876
112	2	\N	command_via_service	success	Command executed via persistent session		interface gpon 0/1\r\ndisplay ont optical-info 0 0	interface gpon 0/1\r\n\r\nMA5683T(config-if-gpon-0/1)#display ont optical-info 0 0\r\n  -----------------------------------------------------------------------------\r\n  ONU NNI port ID                        : 0\r\n  Module type                            : GPON/EPON\r\n  Module sub-type                        : Class B+ and PX20+\r\n  Used type                              : ONU\r\n  Encapsulation Type                     : BOSA ON BOARD\r\n  Optical power precision(dBm)           : 3.0\r\n  Vendor name                            : HUAWEI          \r\n  Vendor rev                             : -\r\n  Vendor PN                              : HW-BOB-0007     \r\n  Vendor SN                              : 1819WB916773P   \r\n  Date Code                              : 18-06-27\r\n  Rx optical power(dBm)                  : -0.69\r\n  Rx power current warning threshold(dBm): [-,-]\r\n  Rx power current alarm threshold(dBm)  : [-29.0,-7.0]\r\n  Tx optical power(dBm)                  : 2.22\r\n  Tx power current warning threshold(dBm): [-,-]\r\n  Tx power current alarm threshold(dBm)  : [0.0,5.0]	1	2025-12-24 10:28:27.589297
113	2	\N	command_via_service	success	Command executed via persistent session		ont reboot 0/1/0 0	[37D                                     [37D  Voltage(V)                             : 3.280\r\n  Supply voltage warning threshold(V)    : [-,-]\r\n  Supply voltage alarm threshold(V)      : [3.000,3.600]\r\n  OLT Rx ONT optical power(dBm)          : -50.00, out of range[-35.00, -15.00]\r\n  CATV Rx optical power(dBm)             : -\r\n  CATV Rx power alarm threshold(dBm)     : [-,-]\r\n  -----------------------------------------------------------------------------\r\n\r\nMA5683T(config-if-gpon-0/1)#ntreboot0/1/00\r\n                            ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config-if-gpon-0/1)#	1	2025-12-24 10:29:27.123649
115	2	\N	command_via_service	failed	Service connection failed: Operation timed out after 60002 milliseconds with 0 bytes received		interface gpon 0/1\r\ndisplay ont optical-info 0 0		1	2025-12-24 10:30:31.952636
116	2	\N	command_via_service	success	Command executed via persistent session		interface gpon 0/1\r\ndisplay ont optical-info 0 0	[37D                                     [37D  Temperature alarm threshold(C)         : [-61,95]	1	2025-12-24 10:30:33.328705
117	2	\N	command_via_service	success	Command executed via persistent session		ont delete 0/1/0 0	ont delete 0/1/00\r\n                                       ^\r\n  % Parameter error, the error locates at '^'\r\n\r\nMA5683T(config-if-gpon-0/1)#\r\n\r\nMA5683T(config-if-gpon-0/1)#	1	2025-12-24 10:31:32.903876
40	2	\N	authorize	failed	Authorization failed for 48575443F2D53A8B		interface gpon 0/1\r\nont add 0 sn-auth 48575443F2D53A8B omci ont-lineprofile-id 10 ont-srvprofile-id 10 desc "TEST_20251222"\r\nquit	interface gpon 0/1\r\n\r\nMA5683T(config-if-gpon-0/1)#ont add 0 sn-auth 48575443F2D53A8B omci ont-lineprof ile-id 10 ont-srvprofile-id 10 desc "TEST_20251222"\r\n  Failure: The line profile does not exist\r\n\r\nMA5683T(config-if-gpon-0/1)#quit\r\n\r\nMA5683T(config)#	1	2025-12-22 12:22:42.141378
44	2	\N	authorize	success	ONU 48575443F2D53A8B authorized as TEST_AUTH_20251222		interface gpon 0/1\r\nont add 0 sn-auth 48575443F2D53A8B omci ont-lineprofile-id 2 ont-srvprofile-id 3 desc "TEST_AUTH_20251222"\r\nquit	interface gpon 0/1\r\n\r\nMA5683T(config-if-gpon-0/1)#ont add 0 sn-auth 48575443F2D53A8B omci ont-lineprof ile-id 2 ont-srvprofile-id 3 desc "TEST_AUTH_20251222"\r\n  Number of ONTs that can be added: 1, success: 1\r\n  PortID :0, ONTID :0\r\n\r\nMA5683T(config-if-gpon-0/1)#quit\r\n\r\nMA5683T(config)#	1	2025-12-22 12:25:10.372913
110	2	\N	authorize	success	ONU 48575443F2D53A8B authorized as TestONU_HG8546M with TR-069		interface gpon 0/1\r\nont add 0 sn-auth 48575443F2D53A8B omci ont-lineprofile-id 2 ont-srvprofile-id 3 desc "TestONU_HG8546M"\r\nquit	interface gpon 0/1\r\n\r\nMA5683T(config-if-gpon-0/1)#ont add 0 sn-auth 48575443F2D53A8B omci ont-lineprof [1Dile-id 2 ont-srvprofile-id 3 desc "TestONU_HG8546M"\r\n  Number of ONTs that can be added: 1, success: 1\r\n  PortID :0, ONTID :0\r\n\r\nMA5683T(config-if-gpon-0/1)#quit\r\n\r\nMA5683T(config)#\r\n\r\nMA5683T(config)#\n\n[TR-069 Config]\ninterface gpon 0/1\r\n\r\nMA5683T(config-if-gpon-0/1)#ont port native-vlan 0 0 eth 1 vlan 69 priority 0\r\n\r\nMA5683T(config-if-gpon-0/1)#ont ipconfig 0 0 ip-index 0 dhcp vlan 69\r\n{ <cr>|priority<K>\nservice-portvlan69gpon0/1/0ont0gemport2multi-service [1Duser-vlanrx-cttr6tx-cttr6\r\n                            ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config-if-gpon-0/1)#	1	2025-12-24 07:53:11.293962
114	2	\N	reboot	success	ONU 48575443F2D53A8B rebooted		ont reboot 0/1/0 0	[37D                                     [37D  Voltage(V)                             : 3.280\r\n  Supply voltage warning threshold(V)    : [-,-]\r\n  Supply voltage alarm threshold(V)      : [3.000,3.600]\r\n  OLT Rx ONT optical power(dBm)          : -50.00, out of range[-35.00, -15.00]\r\n  CATV Rx optical power(dBm)             : -\r\n  CATV Rx power alarm threshold(dBm)     : [-,-]\r\n  -----------------------------------------------------------------------------\r\n\r\nMA5683T(config-if-gpon-0/1)#ntreboot0/1/00\r\n                            ^\r\n  % Unknown command, the error locates at '^'\r\n\r\nMA5683T(config-if-gpon-0/1)#	1	2025-12-24 10:29:27.407641
\.


--
-- Data for Name: huawei_service_profiles; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_service_profiles (id, name, description, profile_type, vlan_id, vlan_mode, speed_profile_up, speed_profile_down, qos_profile, gem_port, tcont_profile, line_profile, srv_profile, native_vlan, additional_config, is_default, is_active, created_at, updated_at, tr069_vlan, tr069_profile_id, tr069_gem_port) FROM stdin;
1	Basic Plan	\N	internet	\N	tag	\N	\N	\N	\N	\N	2	3	\N	\N	f	t	2025-12-22 12:22:27.361201	2025-12-22 12:22:27.361201	\N	\N	2
2	HG8546M	Huawei HG8546M Router	internet	\N	tag	\N	\N	\N	\N	\N	2	3	\N	\N	t	t	2025-12-24 07:00:52.17554	2025-12-24 07:00:52.17554	69	\N	2
3	HG8247	Huawei HG8247 Router	internet	\N	tag	\N	\N	\N	\N	\N	2	2	\N	\N	f	t	2025-12-24 07:00:52.17554	2025-12-24 07:00:52.17554	69	\N	2
4	HG8145V5	Huawei HG8145V5 Router	internet	\N	tag	\N	\N	\N	\N	\N	2	5	\N	\N	f	t	2025-12-24 07:00:52.17554	2025-12-24 07:00:52.17554	69	\N	2
5	HG8145V	Huawei HG8145V Router	internet	\N	tag	\N	\N	\N	\N	\N	2	6	\N	\N	f	t	2025-12-24 07:00:52.17554	2025-12-24 07:00:52.17554	69	\N	2
6	HG8447	Huawei HG8447 Router	internet	\N	tag	\N	\N	\N	\N	\N	2	7	\N	\N	f	t	2025-12-24 07:00:52.17554	2025-12-24 07:00:52.17554	69	\N	2
7	HG8245H	Huawei HG8245H Router	internet	\N	tag	\N	\N	\N	\N	\N	2	9	\N	\N	f	t	2025-12-24 07:00:52.17554	2025-12-24 07:00:52.17554	69	\N	2
8	HG6143D	Huawei HG6143D Router	internet	\N	tag	\N	\N	\N	\N	\N	2	10	\N	\N	f	t	2025-12-24 07:00:52.17554	2025-12-24 07:00:52.17554	69	\N	2
9	HS8145V	Huawei HS8145V Router	internet	\N	tag	\N	\N	\N	\N	\N	2	11	\N	\N	f	t	2025-12-24 07:00:52.17554	2025-12-24 07:00:52.17554	69	\N	2
10	HS8546V	Huawei HS8546V Router	internet	\N	tag	\N	\N	\N	\N	\N	2	12	\N	\N	f	t	2025-12-24 07:00:52.17554	2025-12-24 07:00:52.17554	69	\N	2
11	HG8346M	Huawei HG8346M Router	internet	\N	tag	\N	\N	\N	\N	\N	2	4	\N	\N	f	t	2025-12-24 07:00:52.17554	2025-12-24 07:00:52.17554	69	\N	2
12	HG865	Huawei HG865 Router	internet	\N	tag	\N	\N	\N	\N	\N	2	14	\N	\N	f	t	2025-12-24 07:00:52.17554	2025-12-24 07:00:52.17554	69	\N	2
13	HG8545M	Huawei HG8545M Router	internet	\N	tag	\N	\N	\N	\N	\N	2	15	\N	\N	f	t	2025-12-24 07:00:52.17554	2025-12-24 07:00:52.17554	69	\N	2
14	HG8145V6	Huawei HG8145V6 Router	internet	\N	tag	\N	\N	\N	\N	\N	2	21	\N	\N	f	t	2025-12-24 07:00:52.17554	2025-12-24 07:00:52.17554	69	\N	2
\.


--
-- Data for Name: huawei_service_templates; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_service_templates (id, name, description, downstream_bandwidth, upstream_bandwidth, bandwidth_unit, vlan_id, vlan_mode, qos_profile, line_profile_id, service_profile_id, iptv_enabled, voip_enabled, tr069_enabled, is_default, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: huawei_subzones; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_subzones (id, zone_id, name, description, is_active, created_at, updated_at) FROM stdin;
1	2	Chamos		t	2025-12-20 16:01:27.686028	2025-12-20 16:01:27.686028
\.


--
-- Data for Name: huawei_uplinks; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_uplinks (id, olt_id, frame, slot, port, port_type, admin_status, oper_status, speed, duplex, vlan_mode, allowed_vlans, pvid, description, synced_at, created_at) FROM stdin;
\.


--
-- Data for Name: huawei_vlans; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_vlans (id, olt_id, vlan_id, vlan_type, attribute, standard_port_count, service_port_count, vlan_connect_count, description, is_management, is_active, created_at, updated_at, is_multicast, is_voip, dhcp_snooping, lan_to_lan, is_tr069) FROM stdin;
6	1	100	smart	common	0	0	0	Internet Service	f	t	2025-12-20 16:10:21.099411	2025-12-20 16:10:21.099411	f	f	f	f	f
7	1	200	smart	common	0	0	0	IPTV	f	t	2025-12-20 16:10:21.099411	2025-12-20 16:10:21.099411	f	f	f	f	f
8	1	300	smart	common	0	0	0	VoIP	f	t	2025-12-20 16:10:21.099411	2025-12-20 16:10:21.099411	f	f	f	f	f
9	1	500	smart	common	0	0	0	Business	f	t	2025-12-20 16:10:21.099411	2025-12-20 16:10:21.099411	f	f	f	f	f
11	2	1	smart	common	6	0	0	\N	f	t	2025-12-22 11:10:59.181829	2025-12-22 11:10:59.181829	f	f	f	f	f
12	2	10	smart	common	0	0	0	\N	f	t	2025-12-22 11:10:59.402447	2025-12-22 11:10:59.402447	f	f	f	f	f
13	2	15	smart	common	1	197	0	\N	f	t	2025-12-22 11:10:59.719523	2025-12-22 11:10:59.719523	f	f	f	f	f
14	2	16	smart	common	1	24	0	\N	f	t	2025-12-22 11:10:59.939287	2025-12-22 11:10:59.939287	f	f	f	f	f
15	2	33	smart	common	1	6	0	\N	f	t	2025-12-22 11:11:00.163303	2025-12-22 11:11:00.163303	f	f	f	f	f
16	2	34	smart	common	1	122	0	\N	f	t	2025-12-22 11:11:00.382496	2025-12-22 11:11:00.382496	f	f	f	f	f
17	2	36	smart	common	1	2	0	\N	f	t	2025-12-22 11:11:00.601631	2025-12-22 11:11:00.601631	f	f	f	f	f
18	2	69	smart	common	1	205	0	\N	f	t	2025-12-22 11:11:00.820548	2025-12-22 11:11:00.820548	f	f	f	f	f
19	2	200	smart	common	1	0	0	\N	f	t	2025-12-22 11:11:01.039431	2025-12-22 11:11:01.039431	f	f	f	f	f
20	2	302	smart	common	1	0	0	\N	f	t	2025-12-22 11:11:01.258442	2025-12-22 11:11:01.258442	f	f	f	f	f
21	2	660	smart	common	1	2	0	\N	f	t	2025-12-22 11:11:01.47819	2025-12-22 11:11:01.47819	f	f	f	f	f
10	2	888	smart	common	0	0	0	Test VLAN	f	t	2025-12-22 11:09:44.215243	2025-12-22 11:11:01.708319	f	f	f	f	f
23	2	903	smart	common	1	1	0	\N	f	t	2025-12-22 11:11:01.926924	2025-12-22 11:11:01.926924	f	f	f	f	f
24	2	999	smart	common	0	0	0	\N	f	t	2025-12-22 11:11:02.14699	2025-12-22 11:11:02.14699	f	f	f	f	f
25	2	980	smart	common	0	0	0	Test VLAN 980	f	t	2025-12-22 11:13:15.115014	2025-12-22 11:13:15.115014	f	f	f	f	f
\.


--
-- Data for Name: huawei_zones; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.huawei_zones (id, name, description, is_active, created_at, updated_at) FROM stdin;
1	Test Zone	Test zone for verification	t	2025-12-20 15:59:56.197839	2025-12-20 15:59:56.197839
2	MANGUO		t	2025-12-20 16:01:11.688211	2025-12-20 16:01:11.688211
\.


--
-- Data for Name: interface_history; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.interface_history (id, interface_id, in_octets, out_octets, in_rate, out_rate, in_errors, out_errors, recorded_at) FROM stdin;
\.


--
-- Data for Name: inventory_audit_items; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_audit_items (id, audit_id, equipment_id, category_id, expected_qty, actual_qty, variance, notes, verified_by, verified_at, created_at) FROM stdin;
\.


--
-- Data for Name: inventory_audits; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_audits (id, audit_number, warehouse_id, audit_type, scheduled_date, completed_date, status, notes, created_by, completed_by, created_at) FROM stdin;
\.


--
-- Data for Name: inventory_locations; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_locations (id, warehouse_id, name, code, type, capacity, notes, is_active, created_at) FROM stdin;
\.


--
-- Data for Name: inventory_loss_reports; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_loss_reports (id, report_number, equipment_id, reported_by, employee_id, loss_type, loss_date, description, estimated_value, investigation_status, investigation_notes, resolved_by, resolved_at, resolution, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: inventory_po_items; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_po_items (id, po_id, category_id, item_name, quantity, unit_price, received_qty, notes, created_at) FROM stdin;
\.


--
-- Data for Name: inventory_purchase_orders; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_purchase_orders (id, po_number, supplier_name, supplier_contact, order_date, expected_date, status, total_amount, notes, created_by, approved_by, approved_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: inventory_receipt_items; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_receipt_items (id, receipt_id, po_item_id, equipment_id, category_id, item_name, quantity, serial_number, mac_address, condition, location_id, unit_cost, notes, created_at) FROM stdin;
\.


--
-- Data for Name: inventory_receipts; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_receipts (id, receipt_number, po_id, warehouse_id, receipt_date, supplier_name, delivery_note, status, notes, received_by, verified_by, verified_at, created_at) FROM stdin;
\.


--
-- Data for Name: inventory_return_items; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_return_items (id, return_id, equipment_id, request_item_id, quantity, condition, location_id, notes, created_at) FROM stdin;
\.


--
-- Data for Name: inventory_returns; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_returns (id, return_number, request_id, returned_by, warehouse_id, return_date, return_type, status, notes, received_by, received_at, created_at) FROM stdin;
\.


--
-- Data for Name: inventory_rma; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_rma (id, rma_number, equipment_id, fault_id, vendor_name, vendor_contact, status, shipped_date, received_date, resolution, resolution_notes, replacement_equipment_id, created_by, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: inventory_stock_levels; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_stock_levels (id, category_id, warehouse_id, min_quantity, max_quantity, reorder_point, is_active, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: inventory_stock_movements; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_stock_movements (id, equipment_id, movement_type, from_location_id, to_location_id, from_warehouse_id, to_warehouse_id, quantity, reference_type, reference_id, notes, performed_by, created_at) FROM stdin;
\.


--
-- Data for Name: inventory_stock_request_items; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_stock_request_items (id, request_id, equipment_id, category_id, item_name, quantity_requested, quantity_approved, quantity_picked, quantity_used, quantity_returned, notes, created_at) FROM stdin;
\.


--
-- Data for Name: inventory_stock_requests; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_stock_requests (id, request_number, requested_by, warehouse_id, request_type, ticket_id, customer_id, priority, status, required_date, notes, approved_by, approved_at, picked_by, picked_at, handed_to, handover_at, handover_signature, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: inventory_thresholds; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_thresholds (id, category_id, warehouse_id, min_quantity, max_quantity, reorder_point, reorder_quantity, notify_on_low, notify_on_excess, is_active, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: inventory_usage; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_usage (id, equipment_id, request_item_id, ticket_id, customer_id, employee_id, job_type, quantity, usage_date, notes, recorded_by, created_at) FROM stdin;
\.


--
-- Data for Name: inventory_warehouses; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.inventory_warehouses (id, name, code, type, address, phone, manager_id, is_active, notes, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: invoice_items; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.invoice_items (id, invoice_id, product_id, description, quantity, unit_price, tax_rate_id, tax_amount, discount_percent, line_total, sort_order) FROM stdin;
\.


--
-- Data for Name: invoices; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.invoices (id, invoice_number, customer_id, order_id, ticket_id, issue_date, due_date, status, subtotal, tax_amount, discount_amount, total_amount, amount_paid, balance_due, currency, notes, terms, is_recurring, recurring_interval, next_recurring_date, created_by, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: late_rules; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.late_rules (id, name, work_start_time, grace_minutes, deduction_tiers, currency, apply_to_department_id, is_default, is_active, created_at, updated_at) FROM stdin;
1	Rule 1	09:00:00	15	[{"amount": 100, "max_minutes": 30, "min_minutes": 16}, {"amount": 200, "max_minutes": 60, "min_minutes": 31}, {"amount": 500, "max_minutes": 9999, "min_minutes": 61}]	KES	\N	t	t	2025-12-04 02:21:39.690375	2025-12-04 02:21:39.690375
\.


--
-- Data for Name: leave_balances; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.leave_balances (id, employee_id, leave_type_id, year, entitled_days, used_days, carried_over, accrued_days, created_at, updated_at, pending_days, carried_over_days, adjusted_days) FROM stdin;
1	1	1	2025	21.00	0.00	0.00	0.00	2025-12-12 23:33:51.748061	2025-12-12 23:44:34.5776	4.00	0.00	0.00
\.


--
-- Data for Name: leave_calendar; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.leave_calendar (id, date, name, is_public_holiday, branch_id, created_at) FROM stdin;
\.


--
-- Data for Name: leave_requests; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.leave_requests (id, employee_id, leave_type_id, start_date, end_date, days_requested, reason, status, approved_by, approved_at, rejection_reason, created_at, updated_at) FROM stdin;
1	1	1	2025-12-22	2025-12-23	2.00	Test request	pending	\N	\N	\N	2025-12-12 23:33:52.195178	2025-12-12 23:33:52.195178
2	1	1	2025-12-25	2025-12-26	2.00	Final test	pending	\N	\N	\N	2025-12-12 23:44:34.106487	2025-12-12 23:44:34.106487
\.


--
-- Data for Name: leave_types; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.leave_types (id, name, code, days_per_year, is_paid, requires_approval, is_active, created_at) FROM stdin;
1	Annual Leave	ANNUAL	21	t	t	t	2025-12-12 18:38:32.968301
2	Sick Leave	SICK	14	t	t	t	2025-12-12 18:38:32.968301
3	Unpaid Leave	UNPAID	0	f	t	t	2025-12-12 18:38:32.968301
4	Maternity Leave	MATERNITY	90	t	t	t	2025-12-12 18:38:32.968301
5	Paternity Leave	PATERNITY	14	t	t	t	2025-12-12 18:38:32.968301
6	Compassionate Leave	COMPASSION	5	t	t	t	2025-12-12 18:38:32.968301
\.


--
-- Data for Name: mobile_notifications; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.mobile_notifications (id, user_id, type, title, message, data, is_read, created_at) FROM stdin;
\.


--
-- Data for Name: mobile_tokens; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.mobile_tokens (id, user_id, token, expires_at, created_at) FROM stdin;
1	1	ebc80eddf8d5b27a908309c195273b00f866209d2d9f8f66d46cf459b6609927	2026-01-04 17:50:15	2025-12-04 06:09:49.325372
3	4	23c2b8fe0cb9151f813464526231a0e1fb25be62f94bde0c371bc836af7ccc67	2026-01-04 18:38:51	2025-12-05 18:22:35.663355
7	5	6369d8733a8e628a0d14dfe6cc950a8e9e4beac34d3f363cbeaeefb064746c58	2026-01-04 19:04:35	2025-12-05 18:57:56.956521
\.


--
-- Data for Name: mpesa_b2b_transactions; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.mpesa_b2b_transactions (id, request_id, conversation_id, originator_conversation_id, sender_shortcode, receiver_shortcode, receiver_type, amount, currency, command_id, account_reference, remarks, status, result_code, result_desc, transaction_id, debit_party_name, credit_party_name, linked_type, linked_id, callback_payload, initiated_by, created_at, updated_at, completed_at) FROM stdin;
\.


--
-- Data for Name: mpesa_b2c_transactions; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.mpesa_b2c_transactions (id, request_id, conversation_id, originator_conversation_id, shortcode, initiator_name, phone, amount, currency, command_id, purpose, remarks, occasion, status, result_code, result_desc, transaction_id, transaction_receipt, receiver_party_public_name, b2c_utility_account_balance, b2c_working_account_balance, linked_type, linked_id, callback_payload, initiated_by, created_at, updated_at, completed_at) FROM stdin;
\.


--
-- Data for Name: mpesa_c2b_transactions; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.mpesa_c2b_transactions (id, transaction_type, trans_id, trans_time, trans_amount, business_short_code, bill_ref_number, invoice_number, org_account_balance, third_party_trans_id, msisdn, first_name, middle_name, last_name, customer_id, status, raw_data, created_at) FROM stdin;
\.


--
-- Data for Name: mpesa_config; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.mpesa_config (id, config_key, config_value, is_encrypted, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: mpesa_transactions; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.mpesa_transactions (id, transaction_type, merchant_request_id, checkout_request_id, result_code, result_desc, mpesa_receipt_number, transaction_date, phone_number, amount, account_reference, transaction_desc, customer_id, invoice_id, status, raw_callback, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: network_devices; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.network_devices (id, name, device_type, vendor, model, ip_address, snmp_version, snmp_community, snmp_port, snmpv3_username, snmpv3_auth_protocol, snmpv3_auth_password, snmpv3_priv_protocol, snmpv3_priv_password, telnet_username, telnet_password, telnet_port, ssh_enabled, ssh_port, location, status, last_polled, poll_interval, enabled, notes, created_at, updated_at) FROM stdin;
2	Martin Muriu	switch	Mikrotik	CRS518	102.205.236.34	v2c	public	161		SHA		AES		noc@superlitecloud.com	Alessia@March19!	2344	t	2244	ROYSAMBU	unknown	\N	300	t		2025-12-06 10:38:19.957836	2025-12-06 10:38:19.957836
3	Martin Muriu	switch	Mikrotik	CRS518	102.205.236.34	v2c	public	161		SHA		AES		noc@superlitecloud.com	Alessia@March19!	2344	t	2244	ROYSAMBU	unknown	\N	300	t		2025-12-06 10:38:35.002559	2025-12-06 10:38:35.002559
4	Martin Muriu	switch	Mikrotik	CRS518	102.205.236.34	v2c	public	161		SHA		AES		noc@superlitecloud.com	Alessia@March19!	2344	t	2244	ROYSAMBU	unknown	\N	300	t		2025-12-06 10:38:50.068221	2025-12-06 10:38:50.068221
5	Martin Muriu	switch	Mikrotik	CRS518	102.205.236.34	v2c	public	161		SHA		AES		noc@superlitecloud.com	Alessia@March19!	2344	t	2244	ROYSAMBU	unknown	\N	300	t		2025-12-06 10:39:22.3847	2025-12-06 10:39:22.3847
1	Martin Muriu	switch	Mikrotik	CRS518	102.205.236.34	v2c	public	161		SHA		AES		noc@superlitecloud.com	Alessia@March19!	2344	t	2244	ROYSAMBU	online	2025-12-06 10:41:35.985786	300	t		2025-12-06 10:38:04.94809	2025-12-06 10:38:04.94809
\.


--
-- Data for Name: onu_discovery_log; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.onu_discovery_log (id, olt_id, serial_number, frame_slot_port, first_seen_at, last_seen_at, notified, notified_at, authorized, authorized_at, onu_type_id, equipment_id) FROM stdin;
1	2	48575443F2D53A8B	0/1/0	2025-12-24 07:43:44.055934	2025-12-24 19:43:01.40538	f	\N	t	2025-12-24 07:52:30.123361	15	HG8546M
\.


--
-- Data for Name: onu_signal_history; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.onu_signal_history (id, onu_id, rx_power, tx_power, status, recorded_at) FROM stdin;
\.


--
-- Data for Name: onu_uptime_log; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.onu_uptime_log (id, onu_id, status, started_at, ended_at, duration_seconds) FROM stdin;
\.


--
-- Data for Name: orders; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.orders (id, order_number, package_id, customer_name, customer_email, customer_phone, customer_address, customer_id, payment_status, payment_method, mpesa_transaction_id, amount, order_status, notes, ticket_id, created_at, updated_at, salesperson_id, commission_paid, lead_source, created_by) FROM stdin;
1	ORD202512041C88	1	Test User	test@example.com	0712345678	123 Test Street	\N	pending	later	\N	1500.00	new		\N	2025-12-04 07:54:19.228462	2025-12-04 07:54:19.228462	\N	f	web	\N
2	ORD20251205F4C7	1	Test Customer	test@example.com	0712345678	123 Test Street	\N	pending	\N	\N	1500.00	new	Test order from CRM	\N	2025-12-05 17:52:35.410118	2025-12-05 17:52:35.410118	\N	f	crm	1
3	ORD-20251205-2334	1	DFFFF	\N	0789786745	westlands	\N	pending	\N	\N	0.00	new		\N	2025-12-05 18:38:22.026419	2025-12-05 18:38:22.026419	1	f	mobile	\N
\.


--
-- Data for Name: payroll; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.payroll (id, employee_id, pay_period_start, pay_period_end, base_salary, overtime_pay, bonuses, deductions, tax, net_pay, status, payment_date, payment_method, notes, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: payroll_commissions; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.payroll_commissions (id, payroll_id, employee_id, commission_type, description, amount, details, created_at) FROM stdin;
\.


--
-- Data for Name: performance_reviews; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.performance_reviews (id, employee_id, reviewer_id, review_period_start, review_period_end, overall_rating, productivity_rating, quality_rating, teamwork_rating, communication_rating, goals_achieved, strengths, areas_for_improvement, goals_next_period, comments, status, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.permissions (id, name, display_name, category, description, created_at) FROM stdin;
97	oms.view	View OMS Dashboard	oms	Can access the OMS (ONU Management System) module	2025-12-22 20:50:35.287007
98	oms.olts	Manage OLTs	oms	Can add, edit, and delete OLT devices	2025-12-22 20:50:35.287007
99	oms.onus	Manage ONUs	oms	Can view, authorize, and configure ONUs	2025-12-22 20:50:35.287007
100	oms.discover	Discover ONUs	oms	Can discover unconfigured ONUs from OLTs	2025-12-22 20:50:35.287007
101	oms.provision	Provision ONUs	oms	Can authorize and provision new ONUs	2025-12-22 20:50:35.287007
102	oms.vlans	Manage VLANs	oms	Can manage VLAN configurations	2025-12-22 20:50:35.287007
103	oms.templates	Manage Service Templates	oms	Can create and edit service templates	2025-12-22 20:50:35.287007
104	oms.vpn	Manage VPN	oms	Can configure WireGuard VPN settings	2025-12-22 20:50:35.287007
105	oms.tr069	Manage TR-069	oms	Can configure TR-069/GenieACS settings	2025-12-22 20:50:35.287007
106	oms.settings	OMS Settings	oms	Can access OMS settings and configuration	2025-12-22 20:50:35.287007
1	dashboard.view	View Dashboard	dashboard	Can view the main dashboard	2025-12-04 02:22:28.116588
2	customers.view	View Customers	customers	Can view customer list and details	2025-12-04 02:22:28.192514
3	customers.create	Create Customers	customers	Can create new customers	2025-12-04 02:22:28.265681
4	customers.edit	Edit Customers	customers	Can edit existing customers	2025-12-04 02:22:28.338804
5	customers.delete	Delete Customers	customers	Can delete customers	2025-12-04 02:22:28.41241
6	tickets.view	View Tickets	tickets	Can view ticket list and details	2025-12-04 02:22:28.485411
7	tickets.create	Create Tickets	tickets	Can create new tickets	2025-12-04 02:22:28.55847
8	tickets.edit	Edit Tickets	tickets	Can edit and update tickets	2025-12-04 02:22:28.631443
9	tickets.delete	Delete Tickets	tickets	Can delete tickets	2025-12-04 02:22:28.70446
10	tickets.assign	Assign Tickets	tickets	Can assign tickets to technicians	2025-12-04 02:22:28.777451
11	hr.view	View HR	hr	Can view employee records and HR data	2025-12-04 02:22:28.850389
12	hr.manage	Manage HR	hr	Can create, edit, and manage employees	2025-12-04 02:22:28.923518
13	hr.payroll	Manage Payroll	hr	Can process payroll and deductions	2025-12-04 02:22:28.996667
14	hr.attendance	Manage Attendance	hr	Can view and edit attendance records	2025-12-04 02:22:29.069699
15	inventory.view	View Inventory	inventory	Can view equipment and inventory	2025-12-04 02:22:29.144204
16	inventory.manage	Manage Inventory	inventory	Can add, edit, and assign equipment	2025-12-04 02:22:29.217686
17	orders.view	View Orders	orders	Can view orders list	2025-12-04 02:22:29.290783
18	orders.create	Create Orders	orders	Can create new orders	2025-12-04 02:22:29.364158
19	orders.manage	Manage Orders	orders	Can edit and process orders	2025-12-04 02:22:29.437129
20	payments.view	View Payments	payments	Can view payment records	2025-12-04 02:22:29.510175
21	payments.manage	Manage Payments	payments	Can process and manage payments	2025-12-04 02:22:29.584136
22	settings.view	View Settings	settings	Can view system settings	2025-12-04 02:22:29.657269
23	settings.manage	Manage Settings	settings	Can modify system settings	2025-12-04 02:22:29.730581
24	settings.sms	Manage SMS Settings	settings	Can configure SMS gateway	2025-12-04 02:22:29.803656
25	settings.biometric	Manage Biometric	settings	Can configure biometric devices	2025-12-04 02:22:29.876816
26	users.view	View Users	users	Can view user accounts	2025-12-04 02:22:29.953828
27	users.manage	Manage Users	users	Can create, edit, and delete users	2025-12-04 02:22:30.027308
28	roles.manage	Manage Roles	users	Can manage roles and permissions	2025-12-04 02:22:30.100269
29	reports.view	View Reports	reports	Can view reports and analytics	2025-12-04 02:22:30.173763
30	reports.export	Export Reports	reports	Can export data and reports	2025-12-04 02:22:30.247263
34	tickets.view_all	View All Tickets	tickets	View all tickets (not just assigned)	2025-12-05 13:53:58.255056
35	customers.view_all	View All Customers	customers	View all customers (not just created by user)	2025-12-05 13:53:58.255056
36	orders.view_all	View All Orders	orders	View all orders (not just owned by user)	2025-12-05 13:53:58.255056
37	complaints.view_all	View All Complaints	complaints	View all complaints (not just assigned)	2025-12-05 13:53:58.255056
38	dashboard.stats	View Dashboard Stats	dashboard	Can view dashboard statistics and metrics	2025-12-11 05:38:43.89736
39	customers.import	Import Customers	customers	Can import customers from CSV/Excel	2025-12-11 05:38:43.89736
40	customers.export	Export Customers	customers	Can export customer data	2025-12-11 05:38:43.89736
41	tickets.escalate	Escalate Tickets	tickets	Can escalate tickets to higher priority	2025-12-11 05:38:43.89736
42	tickets.close	Close Tickets	tickets	Can close/resolve tickets	2025-12-11 05:38:43.89736
43	tickets.reopen	Reopen Tickets	tickets	Can reopen closed tickets	2025-12-11 05:38:43.89736
44	tickets.sla	Manage SLA	tickets	Can configure SLA policies	2025-12-11 05:38:43.89736
45	tickets.commission	Manage Ticket Commission	tickets	Can configure ticket commission rates	2025-12-11 05:38:43.89736
46	hr.advances	Manage Salary Advances	hr	Can approve and manage salary advances	2025-12-11 05:38:43.89736
47	hr.leave	Manage Leave	hr	Can approve and manage leave requests	2025-12-11 05:38:43.89736
48	hr.overtime	Manage Overtime	hr	Can manage overtime and deductions	2025-12-11 05:38:43.89736
49	inventory.import	Import Inventory	inventory	Can import equipment from CSV/Excel	2025-12-11 05:38:43.89736
50	inventory.export	Export Inventory	inventory	Can export inventory data	2025-12-11 05:38:43.89736
51	inventory.assign	Assign Equipment	inventory	Can assign equipment to customers	2025-12-11 05:38:43.89736
52	inventory.faults	Manage Faults	inventory	Can report and manage equipment faults	2025-12-11 05:38:43.89736
53	orders.delete	Delete Orders	orders	Can delete orders	2025-12-11 05:38:43.89736
54	orders.convert	Convert Orders	orders	Can convert orders to tickets	2025-12-11 05:38:43.89736
55	payments.stk	Send STK Push	payments	Can send M-Pesa STK Push requests	2025-12-11 05:38:43.89736
56	payments.refund	Process Refunds	payments	Can process payment refunds	2025-12-11 05:38:43.89736
57	payments.export	Export Payments	payments	Can export payment data	2025-12-11 05:38:43.89736
58	complaints.view	View Complaints	complaints	Can view complaints list	2025-12-11 05:38:43.89736
59	complaints.create	Create Complaints	complaints	Can create new complaints	2025-12-11 05:38:43.89736
60	complaints.edit	Edit Complaints	complaints	Can edit complaints	2025-12-11 05:38:43.89736
61	complaints.approve	Approve Complaints	complaints	Can approve complaints	2025-12-11 05:38:43.89736
62	complaints.reject	Reject Complaints	complaints	Can reject complaints	2025-12-11 05:38:43.89736
63	complaints.convert	Convert to Ticket	complaints	Can convert complaints to tickets	2025-12-11 05:38:43.89736
64	sales.view	View Sales	sales	Can view sales dashboard	2025-12-11 05:38:43.89736
65	sales.manage	Manage Sales	sales	Can manage salesperson assignments	2025-12-11 05:38:43.89736
66	sales.commission	View Commission	sales	Can view and manage commissions	2025-12-11 05:38:43.89736
67	sales.leads	Manage Leads	sales	Can create and manage leads	2025-12-11 05:38:43.89736
68	sales.targets	Manage Targets	sales	Can set and manage sales targets	2025-12-11 05:38:43.89736
69	branches.view	View Branches	branches	Can view branch list	2025-12-11 05:38:43.89736
70	branches.create	Create Branches	branches	Can create new branches	2025-12-11 05:38:43.89736
71	branches.edit	Edit Branches	branches	Can edit branch details	2025-12-11 05:38:43.89736
72	branches.delete	Delete Branches	branches	Can delete branches	2025-12-11 05:38:43.89736
73	branches.assign	Assign Employees	branches	Can assign employees to branches	2025-12-11 05:38:43.89736
74	network.view	View Network	network	Can view SmartOLT network status	2025-12-11 05:38:43.89736
75	network.manage	Manage Network	network	Can manage ONUs and network devices	2025-12-11 05:38:43.89736
76	network.provision	Provision Devices	network	Can provision new network devices	2025-12-11 05:38:43.89736
77	accounting.view	View Accounting	accounting	Can view accounting dashboard	2025-12-11 05:38:43.89736
78	accounting.invoices	Manage Invoices	accounting	Can create and manage invoices	2025-12-11 05:38:43.89736
79	accounting.quotes	Manage Quotes	accounting	Can create and manage quotes	2025-12-11 05:38:43.89736
80	accounting.bills	Manage Bills	accounting	Can manage vendor bills	2025-12-11 05:38:43.89736
81	accounting.expenses	Manage Expenses	accounting	Can record and manage expenses	2025-12-11 05:38:43.89736
82	accounting.vendors	Manage Vendors	accounting	Can manage vendors/suppliers	2025-12-11 05:38:43.89736
83	accounting.products	Manage Products	accounting	Can manage products/services catalog	2025-12-11 05:38:43.89736
84	accounting.reports	View Financial Reports	accounting	Can view P&L, aging reports	2025-12-11 05:38:43.89736
85	accounting.chart	Manage Chart of Accounts	accounting	Can manage chart of accounts	2025-12-11 05:38:43.89736
86	whatsapp.view	View WhatsApp	whatsapp	Can view WhatsApp conversations	2025-12-11 05:38:43.89736
87	whatsapp.send	Send WhatsApp	whatsapp	Can send WhatsApp messages	2025-12-11 05:38:43.89736
88	whatsapp.manage	Manage WhatsApp	whatsapp	Can configure WhatsApp settings	2025-12-11 05:38:43.89736
89	devices.view	View Devices	devices	Can view biometric devices	2025-12-11 05:38:43.89736
90	devices.manage	Manage Devices	devices	Can add/edit biometric devices	2025-12-11 05:38:43.89736
91	devices.sync	Sync Devices	devices	Can sync attendance from devices	2025-12-11 05:38:43.89736
92	devices.enroll	Enroll Users	devices	Can enroll fingerprints on devices	2025-12-11 05:38:43.89736
93	teams.view	View Teams	teams	Can view team list	2025-12-11 05:38:43.89736
94	teams.manage	Manage Teams	teams	Can create and manage teams	2025-12-11 05:38:43.89736
95	logs.view	View Activity Logs	logs	Can view system activity logs	2025-12-11 05:38:43.89736
96	logs.export	Export Logs	logs	Can export activity logs	2025-12-11 05:38:43.89736
\.


--
-- Data for Name: products_services; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.products_services (id, code, name, description, type, unit_price, cost_price, tax_rate_id, income_account_id, expense_account_id, is_active, created_at) FROM stdin;
\.


--
-- Data for Name: public_holidays; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.public_holidays (id, name, holiday_date, is_recurring, created_at) FROM stdin;
\.


--
-- Data for Name: purchase_order_items; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.purchase_order_items (id, purchase_order_id, product_id, equipment_id, description, quantity, received_quantity, unit_price, tax_rate_id, tax_amount, line_total, sort_order) FROM stdin;
\.


--
-- Data for Name: purchase_orders; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.purchase_orders (id, po_number, vendor_id, order_date, expected_date, status, subtotal, tax_amount, total_amount, currency, notes, approved_by, approved_at, created_by, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: quote_items; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.quote_items (id, quote_id, product_id, description, quantity, unit_price, tax_rate_id, tax_amount, discount_percent, line_total, sort_order) FROM stdin;
\.


--
-- Data for Name: quotes; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.quotes (id, quote_number, customer_id, issue_date, expiry_date, status, subtotal, tax_amount, discount_amount, total_amount, currency, notes, terms, converted_to_invoice_id, created_by, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: role_permissions; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.role_permissions (id, role_id, permission_id, created_at) FROM stdin;
1	1	1	2025-12-04 02:22:30.827553
2	1	2	2025-12-04 02:22:30.903482
3	1	3	2025-12-04 02:22:30.976679
4	1	4	2025-12-04 02:22:31.049848
5	1	5	2025-12-04 02:22:31.12304
6	1	6	2025-12-04 02:22:31.196201
7	1	7	2025-12-04 02:22:31.270739
8	1	8	2025-12-04 02:22:31.343933
9	1	9	2025-12-04 02:22:31.417406
10	1	10	2025-12-04 02:22:31.490437
11	1	11	2025-12-04 02:22:31.563595
12	1	12	2025-12-04 02:22:31.637018
13	1	13	2025-12-04 02:22:31.710152
14	1	14	2025-12-04 02:22:31.786503
15	1	15	2025-12-04 02:22:31.85963
16	1	16	2025-12-04 02:22:31.932674
17	1	17	2025-12-04 02:22:32.005832
18	1	18	2025-12-04 02:22:32.078928
19	1	19	2025-12-04 02:22:32.152029
20	1	20	2025-12-04 02:22:32.225117
21	1	21	2025-12-04 02:22:32.299386
22	1	22	2025-12-04 02:22:32.372395
23	1	23	2025-12-04 02:22:32.445505
24	1	24	2025-12-04 02:22:32.51882
25	1	25	2025-12-04 02:22:32.592758
26	1	26	2025-12-04 02:22:32.666501
27	1	27	2025-12-04 02:22:32.739601
28	1	28	2025-12-04 02:22:32.812661
29	1	29	2025-12-04 02:22:32.886091
30	1	30	2025-12-04 02:22:32.959185
111	2	1	2025-12-05 12:30:14.735036
112	2	2	2025-12-05 12:30:14.735036
113	2	3	2025-12-05 12:30:14.735036
114	2	4	2025-12-05 12:30:14.735036
115	2	5	2025-12-05 12:30:14.735036
116	2	6	2025-12-05 12:30:14.735036
117	2	7	2025-12-05 12:30:14.735036
118	2	8	2025-12-05 12:30:14.735036
119	2	9	2025-12-05 12:30:14.735036
120	2	10	2025-12-05 12:30:14.735036
121	2	11	2025-12-05 12:30:14.735036
122	2	12	2025-12-05 12:30:14.735036
123	2	13	2025-12-05 12:30:14.735036
124	2	14	2025-12-05 12:30:14.735036
125	2	15	2025-12-05 12:30:14.735036
126	2	16	2025-12-05 12:30:14.735036
127	2	17	2025-12-05 12:30:14.735036
128	2	18	2025-12-05 12:30:14.735036
129	2	19	2025-12-05 12:30:14.735036
130	2	20	2025-12-05 12:30:14.735036
131	2	21	2025-12-05 12:30:14.735036
132	2	26	2025-12-05 12:30:14.735036
133	2	29	2025-12-05 12:30:14.735036
134	2	30	2025-12-05 12:30:14.735036
135	3	1	2025-12-05 12:30:14.735036
136	3	2	2025-12-05 12:30:14.735036
137	3	6	2025-12-05 12:30:14.735036
138	3	7	2025-12-05 12:30:14.735036
139	3	8	2025-12-05 12:30:14.735036
140	3	14	2025-12-05 12:30:14.735036
141	3	15	2025-12-05 12:30:14.735036
142	3	17	2025-12-05 12:30:14.735036
143	4	1	2025-12-05 12:30:14.735036
144	4	2	2025-12-05 12:30:14.735036
145	4	3	2025-12-05 12:30:14.735036
146	4	4	2025-12-05 12:30:14.735036
147	4	6	2025-12-05 12:30:14.735036
148	4	7	2025-12-05 12:30:14.735036
149	4	14	2025-12-05 12:30:14.735036
150	4	17	2025-12-05 12:30:14.735036
151	4	18	2025-12-05 12:30:14.735036
152	4	19	2025-12-05 12:30:14.735036
153	4	20	2025-12-05 12:30:14.735036
154	5	1	2025-12-05 12:30:14.735036
155	5	2	2025-12-05 12:30:14.735036
156	5	6	2025-12-05 12:30:14.735036
157	5	15	2025-12-05 12:30:14.735036
158	5	17	2025-12-05 12:30:14.735036
159	5	20	2025-12-05 12:30:14.735036
160	5	29	2025-12-05 12:30:14.735036
161	1	34	2025-12-05 13:54:18.357074
162	2	34	2025-12-05 13:54:18.357074
163	1	35	2025-12-05 13:54:18.357074
164	2	35	2025-12-05 13:54:18.357074
165	1	36	2025-12-05 13:54:18.357074
166	2	36	2025-12-05 13:54:18.357074
167	1	37	2025-12-05 13:54:18.357074
168	2	37	2025-12-05 13:54:18.357074
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.roles (id, name, display_name, description, is_system, created_at, updated_at) FROM stdin;
1	admin	Administrator	Full system access with all permissions	t	2025-12-04 02:22:27.592904	2025-12-04 02:22:27.592904
2	manager	Manager	Can manage most resources but limited system settings	t	2025-12-04 02:22:27.680396	2025-12-04 02:22:27.680396
3	technician	Technician	Can manage tickets, customers, and basic operations	t	2025-12-04 02:22:27.753645	2025-12-04 02:22:27.753645
4	salesperson	Salesperson	Can manage orders, leads, and view commissions	t	2025-12-04 02:22:27.826571	2025-12-04 02:22:27.826571
5	viewer	Viewer	Read-only access to most resources	t	2025-12-04 02:22:27.899785	2025-12-04 02:22:27.899785
\.


--
-- Data for Name: salary_advance_repayments; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.salary_advance_repayments (id, advance_id, amount, repayment_date, payroll_id, notes, created_at) FROM stdin;
\.


--
-- Data for Name: salary_advances; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.salary_advances (id, employee_id, requested_amount, approved_amount, repayment_schedule, installments, outstanding_balance, status, requested_at, approved_by, approved_at, disbursed_at, notes, created_at, updated_at, mpesa_b2c_transaction_id, disbursement_status) FROM stdin;
2	1	1000.00	\N	monthly	1	1000.00	pending	2025-12-12 23:44:34.878031	\N	\N	\N	Test	2025-12-12 23:44:34.878031	2025-12-12 23:44:34.878031	\N	pending
1	1	5000.00	\N	monthly	2	5000.00	approved	2025-12-12 23:33:52.927901	1	2025-12-24 17:37:36.110625	\N	Test advance	2025-12-12 23:33:52.927901	2025-12-24 17:37:36.110625	\N	pending
\.


--
-- Data for Name: sales_commissions; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.sales_commissions (id, salesperson_id, order_id, order_amount, commission_type, commission_rate, commission_amount, status, paid_at, notes, created_at) FROM stdin;
\.


--
-- Data for Name: salespersons; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.salespersons (id, employee_id, user_id, name, email, phone, commission_type, commission_value, total_sales, total_commission, is_active, notes, created_at, updated_at) FROM stdin;
1	\N	4	Test Salesperson	sales@test.com	0712345678	percentage	10.00	0.00	0.00	t	\N	2025-12-05 18:25:11.995421	2025-12-05 18:25:11.995421
2	\N	5	john muthee	john@superlite.co.ke	0767908989	percentage	10.00	0.00	0.00	t	\N	2025-12-05 19:00:25.933513	2025-12-05 19:00:25.933513
\.


--
-- Data for Name: schema_migrations; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.schema_migrations (id, version, applied_at) FROM stdin;
1	v2024121901	2025-12-20 00:13:11.151052
2	v2024122002	2025-12-20 00:28:25.522067
3	v2024122003	2025-12-20 14:58:56.888887
4	v2024122004	2025-12-20 15:06:13.232444
5	v2024122205	2025-12-22 15:08:40.267339
6	v2024122206	2025-12-22 15:24:13.78454
7	v2024122207	2025-12-22 15:44:40.89441
8	v2024122208	2025-12-22 16:25:49.121562
\.


--
-- Data for Name: service_fee_types; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.service_fee_types (id, name, description, default_amount, currency, is_active, display_order, created_at, updated_at) FROM stdin;
1	Installation Fee	Fee for new installation of services	1500.00	KES	t	1	2025-12-16 07:42:15.244885	2025-12-16 07:42:15.244885
2	Reconnection Fee	Fee for reconnecting suspended service	500.00	KES	t	2	2025-12-16 07:42:15.244885	2025-12-16 07:42:15.244885
3	Relocation Fee	Fee for relocating customer equipment	2000.00	KES	t	3	2025-12-16 07:42:15.244885	2025-12-16 07:42:15.244885
4	Equipment Rental	Monthly equipment rental fee	300.00	KES	t	4	2025-12-16 07:42:15.244885	2025-12-16 07:42:15.244885
5	Router Configuration	Fee for router setup and configuration	500.00	KES	t	5	2025-12-16 07:42:15.244885	2025-12-16 07:42:15.244885
6	Cable Extension	Fee for additional cabling work	1000.00	KES	t	6	2025-12-16 07:42:15.244885	2025-12-16 07:42:15.244885
7	Site Survey	Fee for site survey and feasibility check	500.00	KES	t	7	2025-12-16 07:42:15.244885	2025-12-16 07:42:15.244885
8	Express Service	Premium fee for expedited service	1000.00	KES	t	8	2025-12-16 07:42:15.244885	2025-12-16 07:42:15.244885
\.


--
-- Data for Name: service_packages; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.service_packages (id, name, slug, description, speed, speed_unit, price, currency, billing_cycle, features, is_popular, is_active, display_order, badge_text, badge_color, icon, created_at, updated_at) FROM stdin;
1	Home Basic	home-basic	Perfect for light internet users	10	Mbps	1500.00	KES	monthly	["Unlimited data", "Free router", "Email support", "1 device connection"]	f	t	1	\N	\N	house	2025-12-03 13:19:13.98947	2025-12-03 13:19:13.98947
2	Home Plus	home-plus	Great for streaming and work from home	30	Mbps	2500.00	KES	monthly	["Unlimited data", "Free router", "24/7 support", "5 device connections", "HD streaming"]	t	t	2	Most Popular	\N	wifi	2025-12-03 13:19:13.98947	2025-12-03 13:19:13.98947
3	Home Pro	home-pro	Ultimate speed for power users	100	Mbps	4500.00	KES	monthly	["Unlimited data", "Free premium router", "Priority 24/7 support", "Unlimited devices", "4K streaming", "Gaming optimized"]	f	t	3	Best Value	\N	rocket	2025-12-03 13:19:13.98947	2025-12-03 13:19:13.98947
4	Business Lite	business-lite	Essential connectivity for small businesses	50	Mbps	5000.00	KES	monthly	["Unlimited data", "Static IP", "Business router", "Priority support", "SLA guarantee"]	f	t	4	\N	\N	building	2025-12-03 13:19:13.98947	2025-12-03 13:19:13.98947
\.


--
-- Data for Name: settings; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.settings (id, setting_key, setting_value, created_at, updated_at) FROM stdin;
1	sms_template_order_confirmation	Dear {customer_name}, your order #{order_number} has been received. Amount: KES {amount}. We will contact you shortly to confirm installation. Thank you for choosing us!	2025-12-05 12:34:20.138755	2025-12-05 12:34:20.138755
2	sms_template_order_accepted	Dear {customer_name}, your order #{order_number} has been ACCEPTED! Our team will contact you within 24 hours to schedule installation. Thank you!	2025-12-05 12:34:20.138755	2025-12-05 12:34:20.138755
3	sms_template_complaint_received	Dear {customer_name}, your complaint #{complaint_number} has been received. Category: {category}. Our team will review and respond within 24 hours. Reference this number for follow-up.	2025-12-05 12:34:20.138755	2025-12-05 12:34:20.138755
4	sms_template_complaint_approved	Dear {customer_name}, your complaint #{complaint_number} has been approved and assigned ticket #{ticket_number}. Our technician will contact you shortly.	2025-12-05 12:34:20.138755	2025-12-05 12:34:20.138755
5	sms_template_ticket_created	ISP Support - Ticket #{ticket_number} created. Subject: {subject}. Status: {status}. We will contact you shortly.	2025-12-05 12:34:20.138755	2025-12-05 12:34:20.138755
6	sms_template_ticket_updated	ISP Support - Ticket #{ticket_number} Status: {status}. {message}	2025-12-05 12:34:20.138755	2025-12-05 12:34:20.138755
7	sms_template_ticket_resolved	ISP Support - Ticket #{ticket_number} has been RESOLVED. Thank you for your patience. Please contact us if you need further assistance.	2025-12-05 12:34:20.138755	2025-12-05 12:34:20.138755
8	sms_template_ticket_assigned	ISP Support - Technician {technician_name} has been assigned to your ticket #{ticket_number}. They will contact you shortly.	2025-12-05 12:34:20.138755	2025-12-05 12:34:20.138755
9	whatsapp_enabled	1	2025-12-05 12:54:01.868437	2025-12-05 12:54:01.868437
10	whatsapp_country_code	254	2025-12-05 12:54:01.868437	2025-12-05 12:54:01.868437
11	wa_template_status_update	Hi {customer_name},\n\nThis is an update on your ticket #{ticket_number}.\n\nCurrent Status: {status}\n\nWe're working on resolving your issue. Thank you for your patience.	2025-12-05 12:57:07.838452	2025-12-05 12:57:07.838452
12	wa_template_need_info	Hi {customer_name},\n\nRegarding ticket #{ticket_number}: {subject}\n\nWe need some additional information to proceed. Could you please provide more details?\n\nThank you.	2025-12-05 12:57:07.838452	2025-12-05 12:57:07.838452
13	wa_template_resolved	Hi {customer_name},\n\nGreat news! Your ticket #{ticket_number} has been resolved.\n\nIf you have any further questions or issues, please don't hesitate to contact us.\n\nThank you for choosing our services!	2025-12-05 12:57:07.838452	2025-12-05 12:57:07.838452
14	wa_template_technician_coming	Hi {customer_name},\n\nRegarding ticket #{ticket_number}:\n\nOur technician is on the way to your location. Please ensure someone is available to receive them.\n\nThank you.	2025-12-05 12:57:07.838452	2025-12-05 12:57:07.838452
15	wa_template_order_confirmation	Hi {customer_name},\n\nThank you for your order #{order_number}!\n\nPackage: {package_name}\nAmount: KES {amount}\n\nWe will contact you shortly to schedule installation.\n\nThank you for choosing our services!	2025-12-05 12:57:07.838452	2025-12-05 12:57:07.838452
16	wa_template_complaint_received	Hi {customer_name},\n\nWe have received your complaint (Ref: {complaint_number}).\n\nCategory: {category}\n\nOur team will review and respond within 24 hours.\n\nThank you for your feedback.	2025-12-05 12:57:07.838452	2025-12-05 12:57:07.838452
17	sms_template_technician_assigned	New Ticket #{ticket_number} assigned to you. Customer: {customer_name} ({customer_phone}). Subject: {subject}. Priority: {priority}. Address: {customer_address}	2025-12-08 09:12:17.703213	2025-12-08 09:12:17.703213
18	sms_template_hr_notice	ISP HR Notice - {subject}: {message}	2025-12-08 09:27:38.063713	2025-12-08 09:27:38.063713
19	wa_template_scheduled	Hi {customer_name},\n\nYour service visit for ticket #{ticket_number} has been scheduled.\n\nPlease confirm if this time works for you.\n\nThank you.	2025-12-09 09:26:54.856857	2025-12-09 09:26:54.856857
20	wa_template_order_processing	Hi {customer_name},\n\nYour order #{order_number} is being processed.\n\nOur team will contact you to schedule the installation.\n\nThank you!	2025-12-09 09:26:54.856857	2025-12-09 09:26:54.856857
21	wa_template_order_installation	Hi {customer_name},\n\nWe're ready to install your service for order #{order_number}.\n\nPlease let us know a convenient time for installation.\n\nThank you!	2025-12-09 09:26:54.856857	2025-12-09 09:26:54.856857
22	wa_template_complaint_review	Hi {customer_name},\n\nRegarding your complaint {complaint_number}:\n\nWe are currently reviewing your issue and will update you soon.\n\nThank you for your patience.	2025-12-09 09:26:54.856857	2025-12-09 09:26:54.856857
23	wa_template_complaint_approved	Hi {customer_name},\n\nYour complaint {complaint_number} has been approved and a support ticket will be created.\n\nOur team will contact you shortly to resolve the issue.\n\nThank you!	2025-12-09 09:26:54.856857	2025-12-09 09:26:54.856857
24	wa_template_complaint_rejected	Hi {customer_name},\n\nRegarding your complaint {complaint_number}:\n\nAfter careful review, we were unable to proceed with this complaint.\n\nIf you have any questions, please contact our support team.\n\nThank you.	2025-12-09 09:26:54.856857	2025-12-09 09:26:54.856857
25	oneisp_api_token	eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyIjp7InVzZXJJZCI6IjJuQ1czNWFsc1hhTktaNVRSdFV2cVR1clN5WCIsInVzZXJUeXBlIjoiSVNQIiwiaGFzTGljZW5jZSI6dHJ1ZX0sImV4cCI6MTc2NTU4MDQxMiwiaWF0IjoxNzY1NTU4ODEyfQ.30KF_jpygNZfm-xk3nf3F5gTnedCit-JjIzrQkXhJIQ	2025-12-12 17:00:55.329243	2025-12-12 17:00:55.329243
26	wa_provisioning_group		2025-12-23 22:48:53.539181	2025-12-23 22:48:53.539181
\.


--
-- Data for Name: sla_business_hours; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.sla_business_hours (id, day_of_week, start_time, end_time, is_working_day, created_at) FROM stdin;
1	1	08:00:00	17:00:00	t	2025-12-04 07:14:55.57465
2	2	08:00:00	17:00:00	t	2025-12-04 07:14:55.647738
3	3	08:00:00	17:00:00	t	2025-12-04 07:14:55.718829
4	4	08:00:00	17:00:00	t	2025-12-04 07:14:55.789985
5	5	08:00:00	17:00:00	t	2025-12-04 07:14:55.861164
6	6	09:00:00	13:00:00	t	2025-12-04 07:14:55.932535
\.


--
-- Data for Name: sla_holidays; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.sla_holidays (id, name, holiday_date, is_recurring, created_at) FROM stdin;
\.


--
-- Data for Name: sla_policies; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.sla_policies (id, name, description, priority, response_time_hours, resolution_time_hours, escalation_time_hours, escalation_to, notify_on_breach, is_active, is_default, created_at, updated_at) FROM stdin;
1	Critical Priority SLA	For critical/emergency issues requiring immediate attention	critical	1	4	2	\N	t	t	t	2025-12-04 07:14:54.868259	2025-12-04 07:14:54.868259
2	Medium Priority SLA	Standard SLA for regular issues	medium	4	24	12	\N	t	t	t	2025-12-04 07:14:55.011082	2025-12-04 07:14:55.011082
\.


--
-- Data for Name: sms_logs; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.sms_logs (id, ticket_id, recipient_phone, recipient_type, message, status, sent_at) FROM stdin;
1	1	+254707256700	customer	Ticket created notification	failed	2025-12-03 10:51:38.8095
2	1	+1234567890	technician	Ticket assignment notification	failed	2025-12-03 10:51:39.67025
3	2	+254707256700	customer	Ticket created notification	sent	2025-12-03 11:43:05.496109
4	2	+1234567890	technician	Ticket assignment notification	failed	2025-12-03 11:43:06.840597
5	3	+254707256700	customer	Ticket created notification	sent	2025-12-04 06:32:10.159511
6	3	0701031531	team_member	Team assignment notification	sent	2025-12-04 06:32:11.658559
\.


--
-- Data for Name: tax_rates; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.tax_rates (id, name, rate, type, is_inclusive, is_default, is_active, created_at) FROM stdin;
1	VAT 16%	16.00	percentage	f	t	t	2025-12-10 12:07:35.923103
2	Exempt	0.00	percentage	f	f	t	2025-12-10 12:07:35.995755
\.


--
-- Data for Name: team_members; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.team_members (id, team_id, employee_id, joined_at) FROM stdin;
1	1	1	2025-12-04 06:27:09.974588
\.


--
-- Data for Name: teams; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.teams (id, name, description, leader_id, is_active, created_at, updated_at, branch_id) FROM stdin;
1	Installation & Maintenance		1	t	2025-12-04 06:26:25.577461	2025-12-04 06:26:25.577461	\N
\.


--
-- Data for Name: technician_kit_items; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.technician_kit_items (id, kit_id, equipment_id, category_id, quantity, issued_quantity, returned_quantity, status, notes, created_at) FROM stdin;
\.


--
-- Data for Name: technician_kits; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.technician_kits (id, kit_number, employee_id, name, description, status, issued_date, issued_by, returned_date, notes, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: ticket_categories; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.ticket_categories (id, key, label, description, color, display_order, is_active, created_at, updated_at) FROM stdin;
1	connectivity	Connectivity Issue	\N	primary	1	t	2025-12-14 12:11:12.342701	2025-12-14 12:11:12.342701
2	speed	Speed Issue	\N	primary	2	t	2025-12-14 12:11:12.342701	2025-12-14 12:11:12.342701
3	installation	New Installation	\N	primary	3	t	2025-12-14 12:11:12.342701	2025-12-14 12:11:12.342701
4	billing	Billing Inquiry	\N	primary	4	t	2025-12-14 12:11:12.342701	2025-12-14 12:11:12.342701
5	equipment	Equipment Problem	\N	primary	5	t	2025-12-14 12:11:12.342701	2025-12-14 12:11:12.342701
6	outage	Service Outage	\N	primary	6	t	2025-12-14 12:11:12.342701	2025-12-14 12:11:12.342701
7	service	Service Quality	\N	primary	7	t	2025-12-14 12:11:12.342701	2025-12-14 12:11:12.342701
8	upgrade	Plan Upgrade	\N	primary	8	t	2025-12-14 12:11:12.342701	2025-12-14 12:11:12.342701
9	other	Other	\N	primary	9	t	2025-12-14 12:11:12.342701	2025-12-14 12:11:12.342701
\.


--
-- Data for Name: ticket_comments; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.ticket_comments (id, ticket_id, user_id, comment, is_internal, created_at) FROM stdin;
\.


--
-- Data for Name: ticket_commission_rates; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.ticket_commission_rates (id, category, rate, currency, description, is_active, created_at, updated_at, require_sla_compliance) FROM stdin;
1	connectivity	100.00	KES	Commission for connectivity issue tickets	t	2025-12-14 12:11:24.094591	2025-12-14 12:11:24.094591	f
2	speed	100.00	KES	Commission for speed issue tickets	t	2025-12-14 12:11:24.094591	2025-12-14 12:11:24.094591	f
3	installation	500.00	KES	Commission for new installation tickets	t	2025-12-14 12:11:24.094591	2025-12-14 12:11:24.094591	f
4	billing	50.00	KES	Commission for billing inquiry tickets	t	2025-12-14 12:11:24.094591	2025-12-14 12:11:24.094591	f
5	equipment	150.00	KES	Commission for equipment problem tickets	t	2025-12-14 12:11:24.094591	2025-12-14 12:11:24.094591	f
6	outage	100.00	KES	Commission for service outage tickets	t	2025-12-14 12:11:24.094591	2025-12-14 12:11:24.094591	f
7	service	100.00	KES	Commission for service quality tickets	t	2025-12-14 12:11:24.094591	2025-12-14 12:11:24.094591	f
8	upgrade	200.00	KES	Commission for plan upgrade tickets	t	2025-12-14 12:11:24.094591	2025-12-14 12:11:24.094591	f
9	other	50.00	KES	Commission for other tickets	t	2025-12-14 12:11:24.094591	2025-12-14 12:11:24.094591	f
\.


--
-- Data for Name: ticket_earnings; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.ticket_earnings (id, ticket_id, employee_id, team_id, category, full_rate, earned_amount, share_count, currency, status, payroll_id, created_at, sla_compliant, sla_note) FROM stdin;
\.


--
-- Data for Name: ticket_escalations; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.ticket_escalations (id, ticket_id, escalated_by, escalated_to, reason, previous_priority, new_priority, previous_assigned_to, status, resolved_at, resolution_notes, created_at) FROM stdin;
\.


--
-- Data for Name: ticket_satisfaction_ratings; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.ticket_satisfaction_ratings (id, ticket_id, customer_id, rating, feedback, rated_by_name, rated_at, created_at) FROM stdin;
\.


--
-- Data for Name: ticket_service_fees; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.ticket_service_fees (id, ticket_id, fee_type_id, fee_name, amount, currency, notes, is_paid, paid_at, payment_reference, created_by, created_at) FROM stdin;
\.


--
-- Data for Name: ticket_sla_logs; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.ticket_sla_logs (id, ticket_id, event_type, details, created_at) FROM stdin;
1	4	sla_assigned	SLA policy applied based on medium priority	2025-12-05 07:23:04.818496
\.


--
-- Data for Name: ticket_status_tokens; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.ticket_status_tokens (id, ticket_id, employee_id, token_hash, allowed_statuses, expires_at, max_uses, used_count, created_at, last_used_at, is_active, token_lookup) FROM stdin;
\.


--
-- Data for Name: ticket_templates; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.ticket_templates (id, name, category, subject, content, is_active, created_by, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: tickets; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.tickets (id, ticket_number, customer_id, assigned_to, subject, description, category, priority, status, created_at, updated_at, resolved_at, team_id, sla_policy_id, first_response_at, sla_response_due, sla_resolution_due, sla_response_breached, sla_resolution_breached, sla_paused_at, sla_paused_duration, source, created_by, is_escalated, escalation_count, satisfaction_rating, closed_at, branch_id) FROM stdin;
1	TKT-20251203-1959	1	1	LOS	LOS MUmbi	connectivity	medium	open	2025-12-03 10:51:38.370628	2025-12-03 10:51:38.370628	\N	\N	\N	\N	\N	\N	f	f	\N	0	internal	\N	f	0	\N	\N	\N
2	TKT-20251203-6980	1	1	INSTALLATION @ Dykan 0700251251	INSTALLATION	connectivity	medium	open	2025-12-03 11:43:04.17453	2025-12-03 11:43:04.17453	\N	\N	\N	\N	\N	\N	f	f	\N	0	internal	\N	f	0	\N	\N	\N
3	TKT-20251204-0727	1	\N	LOS	LOS Zimmerman	connectivity	medium	open	2025-12-04 06:32:09.23875	2025-12-04 06:32:09.23875	\N	1	\N	\N	\N	\N	f	f	\N	0	internal	\N	f	0	\N	\N	\N
4	TKT-20251205-8673	2	\N	Billing Problem	I need help with my bill\n\nLocation: Westlands\n\nSubmitted via: Public Complaint Form	billing	medium	open	2025-12-05 07:23:04.818496	2025-12-05 07:23:04.818496	\N	\N	2	\N	2025-12-05 13:00:00	2025-12-09 10:00:00	f	f	\N	0	complaint	\N	f	0	\N	\N	\N
\.


--
-- Data for Name: tr069_devices; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.tr069_devices (id, onu_id, device_id, serial_number, manufacturer, model, last_inform, created_at, updated_at, ip_address) FROM stdin;
\.


--
-- Data for Name: user_notifications; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.user_notifications (id, user_id, type, title, message, reference_id, is_read, created_at, link) FROM stdin;
1	3	success	Salary Advance APPROVED	Your salary advance request for KES 5,000.00 has been approved.	\N	f	2025-12-24 17:37:37.254888	\N
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.users (id, name, email, phone, role, created_at, password_hash, role_id) FROM stdin;
2	John Tech	john@isp.com	+1234567891	technician	2025-12-03 10:40:53.018172	$2y$12$dPvPnyC6u3qJNOPdae4ysuo4DjW3XjlyF0uA4dRIQWgRXd5JzRx02	3
3	Jane Support	jane@isp.com	+1234567892	technician	2025-12-03 10:40:53.018172	$2y$12$dPvPnyC6u3qJNOPdae4ysuo4DjW3XjlyF0uA4dRIQWgRXd5JzRx02	3
1	Admin User	admin@isp.com	+1234567890	admin	2025-12-03 10:40:53.018172	$2y$12$mvYvVF7Vd1ySeVbgaQmUo.R0NR4Zahe95vaQukJqzqDXfo1cCV15C	1
4	Test Salesperson	sales@test.com	0712345678	salesperson	2025-12-05 18:20:02.931903	$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi	4
5	john muthee	john@superlite.co.ke	0767908989	salesperson	2025-12-05 18:56:12.703624	$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi	4
\.


--
-- Data for Name: vendor_bill_items; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.vendor_bill_items (id, bill_id, account_id, description, quantity, unit_price, tax_rate_id, tax_amount, line_total, sort_order) FROM stdin;
\.


--
-- Data for Name: vendor_bills; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.vendor_bills (id, bill_number, vendor_id, purchase_order_id, bill_date, due_date, status, subtotal, tax_amount, total_amount, amount_paid, balance_due, currency, reference, notes, created_by, created_at, updated_at, reminder_enabled, reminder_days_before, last_reminder_sent, reminder_count) FROM stdin;
\.


--
-- Data for Name: vendor_payments; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.vendor_payments (id, payment_number, vendor_id, bill_id, payment_date, amount, payment_method, reference, notes, status, created_by, created_at) FROM stdin;
\.


--
-- Data for Name: vendors; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.vendors (id, name, contact_person, email, phone, address, city, country, tax_pin, payment_terms, currency, notes, is_active, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: vlan_history; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.vlan_history (id, vlan_record_id, in_octets, out_octets, in_rate, out_rate, recorded_at) FROM stdin;
\.


--
-- Data for Name: whatsapp_conversations; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.whatsapp_conversations (id, chat_id, phone, contact_name, customer_id, is_group, unread_count, last_message_at, last_message_preview, status, assigned_to, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: whatsapp_logs; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.whatsapp_logs (id, ticket_id, recipient_phone, recipient_type, message, status, sent_at, order_id, complaint_id, message_type) FROM stdin;
\.


--
-- Data for Name: whatsapp_messages; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.whatsapp_messages (id, conversation_id, message_id, direction, sender_phone, sender_name, message_type, body, media_url, media_mime_type, media_filename, is_read, is_delivered, sent_by, "timestamp", raw_data, created_at) FROM stdin;
\.


--
-- Data for Name: wireguard_peers; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.wireguard_peers (id, server_id, name, description, public_key, private_key_encrypted, preshared_key_encrypted, allowed_ips, endpoint, persistent_keepalive, last_handshake_at, rx_bytes, tx_bytes, is_active, is_olt_site, olt_id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: wireguard_servers; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.wireguard_servers (id, name, enabled, interface_name, interface_addr, listen_port, public_key, private_key_encrypted, preshared_key_encrypted, mtu, dns_servers, post_up_cmd, post_down_cmd, health_status, last_handshake_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: wireguard_settings; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.wireguard_settings (id, setting_key, setting_value, created_at, updated_at) FROM stdin;
1	vpn_enabled	false	2025-12-22 16:25:46.611203	2025-12-22 16:25:46.611203
2	tr069_use_vpn_gateway	false	2025-12-22 16:25:46.686869	2025-12-22 16:25:46.686869
3	tr069_acs_url	http://localhost:7547	2025-12-22 16:25:46.76047	2025-12-22 16:25:46.76047
4	vpn_gateway_ip	10.200.0.1	2025-12-22 16:25:46.833819	2025-12-22 16:25:46.833819
5	vpn_network	10.200.0.0/24	2025-12-22 16:25:46.907704	2025-12-22 16:25:46.907704
\.


--
-- Data for Name: wireguard_subnets; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.wireguard_subnets (id, vpn_peer_id, network_cidr, description, subnet_type, is_olt_management, is_tr069_range, is_active, created_at) FROM stdin;
\.


--
-- Data for Name: wireguard_sync_logs; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.wireguard_sync_logs (id, server_id, success, message, synced_at) FROM stdin;
\.


--
-- Name: accounting_settings_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.accounting_settings_id_seq', 11, true);


--
-- Name: activity_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.activity_logs_id_seq', 5, true);


--
-- Name: announcement_recipients_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.announcement_recipients_id_seq', 1, false);


--
-- Name: announcements_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.announcements_id_seq', 1, false);


--
-- Name: attendance_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.attendance_id_seq', 1, false);


--
-- Name: attendance_notification_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.attendance_notification_logs_id_seq', 1, false);


--
-- Name: bill_reminders_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.bill_reminders_id_seq', 1, false);


--
-- Name: biometric_attendance_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.biometric_attendance_logs_id_seq', 1, false);


--
-- Name: biometric_devices_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.biometric_devices_id_seq', 1, true);


--
-- Name: branch_employees_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.branch_employees_id_seq', 1, false);


--
-- Name: branches_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.branches_id_seq', 1, true);


--
-- Name: chart_of_accounts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.chart_of_accounts_id_seq', 24, true);


--
-- Name: company_settings_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.company_settings_id_seq', 31, true);


--
-- Name: complaints_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.complaints_id_seq', 8, true);


--
-- Name: customer_payments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.customer_payments_id_seq', 1, false);


--
-- Name: customer_ticket_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.customer_ticket_tokens_id_seq', 1, false);


--
-- Name: customers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.customers_id_seq', 2, true);


--
-- Name: departments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.departments_id_seq', 1, false);


--
-- Name: device_interfaces_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.device_interfaces_id_seq', 1, false);


--
-- Name: device_monitoring_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.device_monitoring_log_id_seq', 1, false);


--
-- Name: device_onus_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.device_onus_id_seq', 1, false);


--
-- Name: device_user_mapping_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.device_user_mapping_id_seq', 1, false);


--
-- Name: device_vlans_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.device_vlans_id_seq', 1, false);


--
-- Name: employee_branches_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.employee_branches_id_seq', 1, false);


--
-- Name: employees_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.employees_id_seq', 8, true);


--
-- Name: equipment_assignments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.equipment_assignments_id_seq', 1, true);


--
-- Name: equipment_categories_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.equipment_categories_id_seq', 12, true);


--
-- Name: equipment_faults_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.equipment_faults_id_seq', 1, false);


--
-- Name: equipment_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.equipment_id_seq', 1, true);


--
-- Name: equipment_lifecycle_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.equipment_lifecycle_logs_id_seq', 1, false);


--
-- Name: equipment_loans_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.equipment_loans_id_seq', 1, false);


--
-- Name: expense_categories_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.expense_categories_id_seq', 10, true);


--
-- Name: expenses_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.expenses_id_seq', 1, false);


--
-- Name: hr_notification_templates_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.hr_notification_templates_id_seq', 8, true);


--
-- Name: huawei_alerts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_alerts_id_seq', 1, false);


--
-- Name: huawei_apartments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_apartments_id_seq', 1, true);


--
-- Name: huawei_boards_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_boards_id_seq', 1, false);


--
-- Name: huawei_odb_units_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_odb_units_id_seq', 1, true);


--
-- Name: huawei_olt_boards_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_olt_boards_id_seq', 1, false);


--
-- Name: huawei_olt_pon_ports_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_olt_pon_ports_id_seq', 1, false);


--
-- Name: huawei_olt_uplinks_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_olt_uplinks_id_seq', 1, false);


--
-- Name: huawei_olt_vlans_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_olt_vlans_id_seq', 1, false);


--
-- Name: huawei_olts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_olts_id_seq', 2, true);


--
-- Name: huawei_onu_mgmt_ips_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_onu_mgmt_ips_id_seq', 1, false);


--
-- Name: huawei_onu_types_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_onu_types_id_seq', 28, true);


--
-- Name: huawei_onus_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_onus_id_seq', 3, true);


--
-- Name: huawei_pon_ports_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_pon_ports_id_seq', 1, false);


--
-- Name: huawei_port_vlans_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_port_vlans_id_seq', 1, false);


--
-- Name: huawei_provisioning_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_provisioning_logs_id_seq', 118, true);


--
-- Name: huawei_service_profiles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_service_profiles_id_seq', 14, true);


--
-- Name: huawei_service_templates_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_service_templates_id_seq', 1, false);


--
-- Name: huawei_subzones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_subzones_id_seq', 1, true);


--
-- Name: huawei_uplinks_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_uplinks_id_seq', 1, false);


--
-- Name: huawei_vlans_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_vlans_id_seq', 25, true);


--
-- Name: huawei_zones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.huawei_zones_id_seq', 2, true);


--
-- Name: interface_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.interface_history_id_seq', 1, false);


--
-- Name: inventory_audit_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_audit_items_id_seq', 1, false);


--
-- Name: inventory_audits_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_audits_id_seq', 1, false);


--
-- Name: inventory_locations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_locations_id_seq', 1, false);


--
-- Name: inventory_loss_reports_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_loss_reports_id_seq', 1, false);


--
-- Name: inventory_po_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_po_items_id_seq', 1, false);


--
-- Name: inventory_purchase_orders_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_purchase_orders_id_seq', 1, false);


--
-- Name: inventory_receipt_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_receipt_items_id_seq', 1, false);


--
-- Name: inventory_receipts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_receipts_id_seq', 1, false);


--
-- Name: inventory_return_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_return_items_id_seq', 1, false);


--
-- Name: inventory_returns_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_returns_id_seq', 1, false);


--
-- Name: inventory_rma_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_rma_id_seq', 1, false);


--
-- Name: inventory_stock_levels_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_stock_levels_id_seq', 1, false);


--
-- Name: inventory_stock_movements_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_stock_movements_id_seq', 1, false);


--
-- Name: inventory_stock_request_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_stock_request_items_id_seq', 1, false);


--
-- Name: inventory_stock_requests_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_stock_requests_id_seq', 1, false);


--
-- Name: inventory_thresholds_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_thresholds_id_seq', 1, false);


--
-- Name: inventory_usage_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_usage_id_seq', 1, false);


--
-- Name: inventory_warehouses_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.inventory_warehouses_id_seq', 1, false);


--
-- Name: invoice_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.invoice_items_id_seq', 1, false);


--
-- Name: invoices_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.invoices_id_seq', 1, false);


--
-- Name: late_rules_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.late_rules_id_seq', 1, true);


--
-- Name: leave_balances_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.leave_balances_id_seq', 1, true);


--
-- Name: leave_calendar_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.leave_calendar_id_seq', 1, false);


--
-- Name: leave_requests_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.leave_requests_id_seq', 2, true);


--
-- Name: leave_types_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.leave_types_id_seq', 6, true);


--
-- Name: mobile_notifications_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.mobile_notifications_id_seq', 1, false);


--
-- Name: mobile_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.mobile_tokens_id_seq', 10, true);


--
-- Name: mpesa_b2b_transactions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.mpesa_b2b_transactions_id_seq', 1, false);


--
-- Name: mpesa_b2c_transactions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.mpesa_b2c_transactions_id_seq', 1, false);


--
-- Name: mpesa_c2b_transactions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.mpesa_c2b_transactions_id_seq', 1, false);


--
-- Name: mpesa_config_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.mpesa_config_id_seq', 4, true);


--
-- Name: mpesa_transactions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.mpesa_transactions_id_seq', 1, false);


--
-- Name: network_devices_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.network_devices_id_seq', 5, true);


--
-- Name: onu_discovery_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.onu_discovery_log_id_seq', 72, true);


--
-- Name: onu_signal_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.onu_signal_history_id_seq', 1, false);


--
-- Name: onu_uptime_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.onu_uptime_log_id_seq', 1, false);


--
-- Name: orders_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.orders_id_seq', 3, true);


--
-- Name: payroll_commissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.payroll_commissions_id_seq', 1, false);


--
-- Name: payroll_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.payroll_id_seq', 1, true);


--
-- Name: performance_reviews_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.performance_reviews_id_seq', 1, false);


--
-- Name: permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.permissions_id_seq', 106, true);


--
-- Name: products_services_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.products_services_id_seq', 1, false);


--
-- Name: public_holidays_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.public_holidays_id_seq', 1, false);


--
-- Name: purchase_order_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.purchase_order_items_id_seq', 1, false);


--
-- Name: purchase_orders_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.purchase_orders_id_seq', 1, false);


--
-- Name: quote_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.quote_items_id_seq', 1, false);


--
-- Name: quotes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.quotes_id_seq', 1, false);


--
-- Name: role_permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.role_permissions_id_seq', 168, true);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.roles_id_seq', 5, true);


--
-- Name: salary_advance_repayments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.salary_advance_repayments_id_seq', 1, false);


--
-- Name: salary_advances_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.salary_advances_id_seq', 2, true);


--
-- Name: sales_commissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.sales_commissions_id_seq', 1, false);


--
-- Name: salespersons_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.salespersons_id_seq', 2, true);


--
-- Name: schema_migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.schema_migrations_id_seq', 8, true);


--
-- Name: service_fee_types_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.service_fee_types_id_seq', 8, true);


--
-- Name: service_packages_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.service_packages_id_seq', 4, true);


--
-- Name: settings_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.settings_id_seq', 26, true);


--
-- Name: sla_business_hours_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.sla_business_hours_id_seq', 6, true);


--
-- Name: sla_holidays_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.sla_holidays_id_seq', 1, false);


--
-- Name: sla_policies_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.sla_policies_id_seq', 2, true);


--
-- Name: sms_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.sms_logs_id_seq', 6, true);


--
-- Name: tax_rates_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.tax_rates_id_seq', 2, true);


--
-- Name: team_members_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.team_members_id_seq', 1, true);


--
-- Name: teams_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.teams_id_seq', 1, true);


--
-- Name: technician_kit_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.technician_kit_items_id_seq', 1, false);


--
-- Name: technician_kits_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.technician_kits_id_seq', 1, false);


--
-- Name: ticket_categories_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.ticket_categories_id_seq', 9, true);


--
-- Name: ticket_comments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.ticket_comments_id_seq', 1, false);


--
-- Name: ticket_commission_rates_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.ticket_commission_rates_id_seq', 9, true);


--
-- Name: ticket_earnings_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.ticket_earnings_id_seq', 1, false);


--
-- Name: ticket_escalations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.ticket_escalations_id_seq', 1, false);


--
-- Name: ticket_satisfaction_ratings_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.ticket_satisfaction_ratings_id_seq', 1, false);


--
-- Name: ticket_service_fees_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.ticket_service_fees_id_seq', 1, false);


--
-- Name: ticket_sla_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.ticket_sla_logs_id_seq', 1, true);


--
-- Name: ticket_status_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.ticket_status_tokens_id_seq', 1, false);


--
-- Name: ticket_templates_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.ticket_templates_id_seq', 1, false);


--
-- Name: tickets_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.tickets_id_seq', 4, true);


--
-- Name: tr069_devices_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.tr069_devices_id_seq', 1, false);


--
-- Name: user_notifications_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.user_notifications_id_seq', 1, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.users_id_seq', 5, true);


--
-- Name: vendor_bill_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.vendor_bill_items_id_seq', 1, false);


--
-- Name: vendor_bills_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.vendor_bills_id_seq', 1, false);


--
-- Name: vendor_payments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.vendor_payments_id_seq', 1, false);


--
-- Name: vendors_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.vendors_id_seq', 1, false);


--
-- Name: vlan_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.vlan_history_id_seq', 1, false);


--
-- Name: whatsapp_conversations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.whatsapp_conversations_id_seq', 1, false);


--
-- Name: whatsapp_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.whatsapp_logs_id_seq', 1, false);


--
-- Name: whatsapp_messages_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.whatsapp_messages_id_seq', 1, false);


--
-- Name: wireguard_peers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.wireguard_peers_id_seq', 1, false);


--
-- Name: wireguard_servers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.wireguard_servers_id_seq', 1, false);


--
-- Name: wireguard_settings_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.wireguard_settings_id_seq', 5, true);


--
-- Name: wireguard_subnets_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.wireguard_subnets_id_seq', 1, false);


--
-- Name: wireguard_sync_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.wireguard_sync_logs_id_seq', 1, false);


--
-- Name: accounting_settings accounting_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.accounting_settings
    ADD CONSTRAINT accounting_settings_pkey PRIMARY KEY (id);


--
-- Name: accounting_settings accounting_settings_setting_key_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.accounting_settings
    ADD CONSTRAINT accounting_settings_setting_key_key UNIQUE (setting_key);


--
-- Name: activity_logs activity_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT activity_logs_pkey PRIMARY KEY (id);


--
-- Name: announcement_recipients announcement_recipients_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.announcement_recipients
    ADD CONSTRAINT announcement_recipients_pkey PRIMARY KEY (id);


--
-- Name: announcements announcements_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.announcements
    ADD CONSTRAINT announcements_pkey PRIMARY KEY (id);


--
-- Name: attendance attendance_employee_id_date_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.attendance
    ADD CONSTRAINT attendance_employee_id_date_key UNIQUE (employee_id, date);


--
-- Name: attendance_notification_logs attendance_notification_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.attendance_notification_logs
    ADD CONSTRAINT attendance_notification_logs_pkey PRIMARY KEY (id);


--
-- Name: attendance attendance_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.attendance
    ADD CONSTRAINT attendance_pkey PRIMARY KEY (id);


--
-- Name: bill_reminders bill_reminders_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.bill_reminders
    ADD CONSTRAINT bill_reminders_pkey PRIMARY KEY (id);


--
-- Name: biometric_attendance_logs biometric_attendance_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.biometric_attendance_logs
    ADD CONSTRAINT biometric_attendance_logs_pkey PRIMARY KEY (id);


--
-- Name: biometric_devices biometric_devices_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.biometric_devices
    ADD CONSTRAINT biometric_devices_pkey PRIMARY KEY (id);


--
-- Name: branch_employees branch_employees_branch_id_employee_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.branch_employees
    ADD CONSTRAINT branch_employees_branch_id_employee_id_key UNIQUE (branch_id, employee_id);


--
-- Name: branch_employees branch_employees_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.branch_employees
    ADD CONSTRAINT branch_employees_pkey PRIMARY KEY (id);


--
-- Name: branches branches_code_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_code_key UNIQUE (code);


--
-- Name: branches branches_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_pkey PRIMARY KEY (id);


--
-- Name: chart_of_accounts chart_of_accounts_code_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.chart_of_accounts
    ADD CONSTRAINT chart_of_accounts_code_key UNIQUE (code);


--
-- Name: chart_of_accounts chart_of_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.chart_of_accounts
    ADD CONSTRAINT chart_of_accounts_pkey PRIMARY KEY (id);


--
-- Name: company_settings company_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.company_settings
    ADD CONSTRAINT company_settings_pkey PRIMARY KEY (id);


--
-- Name: company_settings company_settings_setting_key_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.company_settings
    ADD CONSTRAINT company_settings_setting_key_key UNIQUE (setting_key);


--
-- Name: complaints complaints_complaint_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.complaints
    ADD CONSTRAINT complaints_complaint_number_key UNIQUE (complaint_number);


--
-- Name: complaints complaints_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.complaints
    ADD CONSTRAINT complaints_pkey PRIMARY KEY (id);


--
-- Name: customer_payments customer_payments_payment_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.customer_payments
    ADD CONSTRAINT customer_payments_payment_number_key UNIQUE (payment_number);


--
-- Name: customer_payments customer_payments_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.customer_payments
    ADD CONSTRAINT customer_payments_pkey PRIMARY KEY (id);


--
-- Name: customer_ticket_tokens customer_ticket_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.customer_ticket_tokens
    ADD CONSTRAINT customer_ticket_tokens_pkey PRIMARY KEY (id);


--
-- Name: customers customers_account_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_account_number_key UNIQUE (account_number);


--
-- Name: customers customers_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_pkey PRIMARY KEY (id);


--
-- Name: departments departments_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_pkey PRIMARY KEY (id);


--
-- Name: device_interfaces device_interfaces_device_id_if_index_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_interfaces
    ADD CONSTRAINT device_interfaces_device_id_if_index_key UNIQUE (device_id, if_index);


--
-- Name: device_interfaces device_interfaces_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_interfaces
    ADD CONSTRAINT device_interfaces_pkey PRIMARY KEY (id);


--
-- Name: device_monitoring_log device_monitoring_log_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_monitoring_log
    ADD CONSTRAINT device_monitoring_log_pkey PRIMARY KEY (id);


--
-- Name: device_onus device_onus_device_id_onu_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_onus
    ADD CONSTRAINT device_onus_device_id_onu_id_key UNIQUE (device_id, onu_id);


--
-- Name: device_onus device_onus_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_onus
    ADD CONSTRAINT device_onus_pkey PRIMARY KEY (id);


--
-- Name: device_user_mapping device_user_mapping_device_id_device_user_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_user_mapping
    ADD CONSTRAINT device_user_mapping_device_id_device_user_id_key UNIQUE (device_id, device_user_id);


--
-- Name: device_user_mapping device_user_mapping_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_user_mapping
    ADD CONSTRAINT device_user_mapping_pkey PRIMARY KEY (id);


--
-- Name: device_vlans device_vlans_device_id_vlan_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_vlans
    ADD CONSTRAINT device_vlans_device_id_vlan_id_key UNIQUE (device_id, vlan_id);


--
-- Name: device_vlans device_vlans_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_vlans
    ADD CONSTRAINT device_vlans_pkey PRIMARY KEY (id);


--
-- Name: employee_branches employee_branches_employee_id_branch_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.employee_branches
    ADD CONSTRAINT employee_branches_employee_id_branch_id_key UNIQUE (employee_id, branch_id);


--
-- Name: employee_branches employee_branches_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.employee_branches
    ADD CONSTRAINT employee_branches_pkey PRIMARY KEY (id);


--
-- Name: employees employees_employee_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_employee_id_key UNIQUE (employee_id);


--
-- Name: employees employees_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_pkey PRIMARY KEY (id);


--
-- Name: equipment_assignments equipment_assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_assignments
    ADD CONSTRAINT equipment_assignments_pkey PRIMARY KEY (id);


--
-- Name: equipment_categories equipment_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_categories
    ADD CONSTRAINT equipment_categories_pkey PRIMARY KEY (id);


--
-- Name: equipment_faults equipment_faults_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_faults
    ADD CONSTRAINT equipment_faults_pkey PRIMARY KEY (id);


--
-- Name: equipment_lifecycle_logs equipment_lifecycle_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_lifecycle_logs
    ADD CONSTRAINT equipment_lifecycle_logs_pkey PRIMARY KEY (id);


--
-- Name: equipment_loans equipment_loans_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_loans
    ADD CONSTRAINT equipment_loans_pkey PRIMARY KEY (id);


--
-- Name: equipment equipment_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_pkey PRIMARY KEY (id);


--
-- Name: equipment equipment_serial_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_serial_number_key UNIQUE (serial_number);


--
-- Name: expense_categories expense_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.expense_categories
    ADD CONSTRAINT expense_categories_pkey PRIMARY KEY (id);


--
-- Name: expenses expenses_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_pkey PRIMARY KEY (id);


--
-- Name: hr_notification_templates hr_notification_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.hr_notification_templates
    ADD CONSTRAINT hr_notification_templates_pkey PRIMARY KEY (id);


--
-- Name: huawei_alerts huawei_alerts_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_alerts
    ADD CONSTRAINT huawei_alerts_pkey PRIMARY KEY (id);


--
-- Name: huawei_apartments huawei_apartments_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_apartments
    ADD CONSTRAINT huawei_apartments_pkey PRIMARY KEY (id);


--
-- Name: huawei_apartments huawei_apartments_zone_id_name_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_apartments
    ADD CONSTRAINT huawei_apartments_zone_id_name_key UNIQUE (zone_id, name);


--
-- Name: huawei_boards huawei_boards_olt_id_slot_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_boards
    ADD CONSTRAINT huawei_boards_olt_id_slot_key UNIQUE (olt_id, slot);


--
-- Name: huawei_boards huawei_boards_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_boards
    ADD CONSTRAINT huawei_boards_pkey PRIMARY KEY (id);


--
-- Name: huawei_odb_units huawei_odb_units_code_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_odb_units
    ADD CONSTRAINT huawei_odb_units_code_key UNIQUE (code);


--
-- Name: huawei_odb_units huawei_odb_units_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_odb_units
    ADD CONSTRAINT huawei_odb_units_pkey PRIMARY KEY (id);


--
-- Name: huawei_olt_boards huawei_olt_boards_olt_id_slot_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olt_boards
    ADD CONSTRAINT huawei_olt_boards_olt_id_slot_key UNIQUE (olt_id, slot);


--
-- Name: huawei_olt_boards huawei_olt_boards_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olt_boards
    ADD CONSTRAINT huawei_olt_boards_pkey PRIMARY KEY (id);


--
-- Name: huawei_olt_pon_ports huawei_olt_pon_ports_olt_id_port_name_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olt_pon_ports
    ADD CONSTRAINT huawei_olt_pon_ports_olt_id_port_name_key UNIQUE (olt_id, port_name);


--
-- Name: huawei_olt_pon_ports huawei_olt_pon_ports_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olt_pon_ports
    ADD CONSTRAINT huawei_olt_pon_ports_pkey PRIMARY KEY (id);


--
-- Name: huawei_olt_uplinks huawei_olt_uplinks_olt_id_port_name_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olt_uplinks
    ADD CONSTRAINT huawei_olt_uplinks_olt_id_port_name_key UNIQUE (olt_id, port_name);


--
-- Name: huawei_olt_uplinks huawei_olt_uplinks_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olt_uplinks
    ADD CONSTRAINT huawei_olt_uplinks_pkey PRIMARY KEY (id);


--
-- Name: huawei_olt_vlans huawei_olt_vlans_olt_id_vlan_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olt_vlans
    ADD CONSTRAINT huawei_olt_vlans_olt_id_vlan_id_key UNIQUE (olt_id, vlan_id);


--
-- Name: huawei_olt_vlans huawei_olt_vlans_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olt_vlans
    ADD CONSTRAINT huawei_olt_vlans_pkey PRIMARY KEY (id);


--
-- Name: huawei_olts huawei_olts_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olts
    ADD CONSTRAINT huawei_olts_pkey PRIMARY KEY (id);


--
-- Name: huawei_onu_mgmt_ips huawei_onu_mgmt_ips_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_onu_mgmt_ips
    ADD CONSTRAINT huawei_onu_mgmt_ips_pkey PRIMARY KEY (id);


--
-- Name: huawei_onu_tr069_config huawei_onu_tr069_config_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_onu_tr069_config
    ADD CONSTRAINT huawei_onu_tr069_config_pkey PRIMARY KEY (onu_id);


--
-- Name: huawei_onu_types huawei_onu_types_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_onu_types
    ADD CONSTRAINT huawei_onu_types_pkey PRIMARY KEY (id);


--
-- Name: huawei_onus huawei_onus_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_onus
    ADD CONSTRAINT huawei_onus_pkey PRIMARY KEY (id);


--
-- Name: huawei_pon_ports huawei_pon_ports_olt_id_frame_slot_port_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_pon_ports
    ADD CONSTRAINT huawei_pon_ports_olt_id_frame_slot_port_key UNIQUE (olt_id, frame, slot, port);


--
-- Name: huawei_pon_ports huawei_pon_ports_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_pon_ports
    ADD CONSTRAINT huawei_pon_ports_pkey PRIMARY KEY (id);


--
-- Name: huawei_port_vlans huawei_port_vlans_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_port_vlans
    ADD CONSTRAINT huawei_port_vlans_pkey PRIMARY KEY (id);


--
-- Name: huawei_provisioning_logs huawei_provisioning_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_provisioning_logs
    ADD CONSTRAINT huawei_provisioning_logs_pkey PRIMARY KEY (id);


--
-- Name: huawei_service_profiles huawei_service_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_service_profiles
    ADD CONSTRAINT huawei_service_profiles_pkey PRIMARY KEY (id);


--
-- Name: huawei_service_templates huawei_service_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_service_templates
    ADD CONSTRAINT huawei_service_templates_pkey PRIMARY KEY (id);


--
-- Name: huawei_subzones huawei_subzones_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_subzones
    ADD CONSTRAINT huawei_subzones_pkey PRIMARY KEY (id);


--
-- Name: huawei_subzones huawei_subzones_zone_id_name_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_subzones
    ADD CONSTRAINT huawei_subzones_zone_id_name_key UNIQUE (zone_id, name);


--
-- Name: huawei_uplinks huawei_uplinks_olt_id_frame_slot_port_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_uplinks
    ADD CONSTRAINT huawei_uplinks_olt_id_frame_slot_port_key UNIQUE (olt_id, frame, slot, port);


--
-- Name: huawei_uplinks huawei_uplinks_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_uplinks
    ADD CONSTRAINT huawei_uplinks_pkey PRIMARY KEY (id);


--
-- Name: huawei_vlans huawei_vlans_olt_id_vlan_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_vlans
    ADD CONSTRAINT huawei_vlans_olt_id_vlan_id_key UNIQUE (olt_id, vlan_id);


--
-- Name: huawei_vlans huawei_vlans_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_vlans
    ADD CONSTRAINT huawei_vlans_pkey PRIMARY KEY (id);


--
-- Name: huawei_zones huawei_zones_name_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_zones
    ADD CONSTRAINT huawei_zones_name_key UNIQUE (name);


--
-- Name: huawei_zones huawei_zones_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_zones
    ADD CONSTRAINT huawei_zones_pkey PRIMARY KEY (id);


--
-- Name: interface_history interface_history_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.interface_history
    ADD CONSTRAINT interface_history_pkey PRIMARY KEY (id);


--
-- Name: inventory_audit_items inventory_audit_items_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_audit_items
    ADD CONSTRAINT inventory_audit_items_pkey PRIMARY KEY (id);


--
-- Name: inventory_audits inventory_audits_audit_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_audits
    ADD CONSTRAINT inventory_audits_audit_number_key UNIQUE (audit_number);


--
-- Name: inventory_audits inventory_audits_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_audits
    ADD CONSTRAINT inventory_audits_pkey PRIMARY KEY (id);


--
-- Name: inventory_locations inventory_locations_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_locations
    ADD CONSTRAINT inventory_locations_pkey PRIMARY KEY (id);


--
-- Name: inventory_loss_reports inventory_loss_reports_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_loss_reports
    ADD CONSTRAINT inventory_loss_reports_pkey PRIMARY KEY (id);


--
-- Name: inventory_loss_reports inventory_loss_reports_report_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_loss_reports
    ADD CONSTRAINT inventory_loss_reports_report_number_key UNIQUE (report_number);


--
-- Name: inventory_po_items inventory_po_items_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_po_items
    ADD CONSTRAINT inventory_po_items_pkey PRIMARY KEY (id);


--
-- Name: inventory_purchase_orders inventory_purchase_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_purchase_orders
    ADD CONSTRAINT inventory_purchase_orders_pkey PRIMARY KEY (id);


--
-- Name: inventory_purchase_orders inventory_purchase_orders_po_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_purchase_orders
    ADD CONSTRAINT inventory_purchase_orders_po_number_key UNIQUE (po_number);


--
-- Name: inventory_receipt_items inventory_receipt_items_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_receipt_items
    ADD CONSTRAINT inventory_receipt_items_pkey PRIMARY KEY (id);


--
-- Name: inventory_receipts inventory_receipts_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_receipts
    ADD CONSTRAINT inventory_receipts_pkey PRIMARY KEY (id);


--
-- Name: inventory_receipts inventory_receipts_receipt_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_receipts
    ADD CONSTRAINT inventory_receipts_receipt_number_key UNIQUE (receipt_number);


--
-- Name: inventory_return_items inventory_return_items_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_return_items
    ADD CONSTRAINT inventory_return_items_pkey PRIMARY KEY (id);


--
-- Name: inventory_returns inventory_returns_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_returns
    ADD CONSTRAINT inventory_returns_pkey PRIMARY KEY (id);


--
-- Name: inventory_returns inventory_returns_return_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_returns
    ADD CONSTRAINT inventory_returns_return_number_key UNIQUE (return_number);


--
-- Name: inventory_rma inventory_rma_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_rma
    ADD CONSTRAINT inventory_rma_pkey PRIMARY KEY (id);


--
-- Name: inventory_rma inventory_rma_rma_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_rma
    ADD CONSTRAINT inventory_rma_rma_number_key UNIQUE (rma_number);


--
-- Name: inventory_stock_levels inventory_stock_levels_category_id_warehouse_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_levels
    ADD CONSTRAINT inventory_stock_levels_category_id_warehouse_id_key UNIQUE (category_id, warehouse_id);


--
-- Name: inventory_stock_levels inventory_stock_levels_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_levels
    ADD CONSTRAINT inventory_stock_levels_pkey PRIMARY KEY (id);


--
-- Name: inventory_stock_movements inventory_stock_movements_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_movements
    ADD CONSTRAINT inventory_stock_movements_pkey PRIMARY KEY (id);


--
-- Name: inventory_stock_request_items inventory_stock_request_items_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_request_items
    ADD CONSTRAINT inventory_stock_request_items_pkey PRIMARY KEY (id);


--
-- Name: inventory_stock_requests inventory_stock_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_requests
    ADD CONSTRAINT inventory_stock_requests_pkey PRIMARY KEY (id);


--
-- Name: inventory_stock_requests inventory_stock_requests_request_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_requests
    ADD CONSTRAINT inventory_stock_requests_request_number_key UNIQUE (request_number);


--
-- Name: inventory_thresholds inventory_thresholds_category_id_warehouse_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_thresholds
    ADD CONSTRAINT inventory_thresholds_category_id_warehouse_id_key UNIQUE (category_id, warehouse_id);


--
-- Name: inventory_thresholds inventory_thresholds_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_thresholds
    ADD CONSTRAINT inventory_thresholds_pkey PRIMARY KEY (id);


--
-- Name: inventory_usage inventory_usage_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_usage
    ADD CONSTRAINT inventory_usage_pkey PRIMARY KEY (id);


--
-- Name: inventory_warehouses inventory_warehouses_code_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_warehouses
    ADD CONSTRAINT inventory_warehouses_code_key UNIQUE (code);


--
-- Name: inventory_warehouses inventory_warehouses_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_warehouses
    ADD CONSTRAINT inventory_warehouses_pkey PRIMARY KEY (id);


--
-- Name: invoice_items invoice_items_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.invoice_items
    ADD CONSTRAINT invoice_items_pkey PRIMARY KEY (id);


--
-- Name: invoices invoices_invoice_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_invoice_number_key UNIQUE (invoice_number);


--
-- Name: invoices invoices_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_pkey PRIMARY KEY (id);


--
-- Name: late_rules late_rules_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.late_rules
    ADD CONSTRAINT late_rules_pkey PRIMARY KEY (id);


--
-- Name: leave_balances leave_balances_employee_id_leave_type_id_year_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.leave_balances
    ADD CONSTRAINT leave_balances_employee_id_leave_type_id_year_key UNIQUE (employee_id, leave_type_id, year);


--
-- Name: leave_balances leave_balances_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.leave_balances
    ADD CONSTRAINT leave_balances_pkey PRIMARY KEY (id);


--
-- Name: leave_calendar leave_calendar_date_branch_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.leave_calendar
    ADD CONSTRAINT leave_calendar_date_branch_id_key UNIQUE (date, branch_id);


--
-- Name: leave_calendar leave_calendar_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.leave_calendar
    ADD CONSTRAINT leave_calendar_pkey PRIMARY KEY (id);


--
-- Name: leave_requests leave_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.leave_requests
    ADD CONSTRAINT leave_requests_pkey PRIMARY KEY (id);


--
-- Name: leave_types leave_types_code_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.leave_types
    ADD CONSTRAINT leave_types_code_key UNIQUE (code);


--
-- Name: leave_types leave_types_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.leave_types
    ADD CONSTRAINT leave_types_pkey PRIMARY KEY (id);


--
-- Name: mobile_notifications mobile_notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mobile_notifications
    ADD CONSTRAINT mobile_notifications_pkey PRIMARY KEY (id);


--
-- Name: mobile_tokens mobile_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mobile_tokens
    ADD CONSTRAINT mobile_tokens_pkey PRIMARY KEY (id);


--
-- Name: mobile_tokens mobile_tokens_user_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mobile_tokens
    ADD CONSTRAINT mobile_tokens_user_id_key UNIQUE (user_id);


--
-- Name: mpesa_b2b_transactions mpesa_b2b_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_b2b_transactions
    ADD CONSTRAINT mpesa_b2b_transactions_pkey PRIMARY KEY (id);


--
-- Name: mpesa_b2b_transactions mpesa_b2b_transactions_request_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_b2b_transactions
    ADD CONSTRAINT mpesa_b2b_transactions_request_id_key UNIQUE (request_id);


--
-- Name: mpesa_b2c_transactions mpesa_b2c_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_b2c_transactions
    ADD CONSTRAINT mpesa_b2c_transactions_pkey PRIMARY KEY (id);


--
-- Name: mpesa_b2c_transactions mpesa_b2c_transactions_request_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_b2c_transactions
    ADD CONSTRAINT mpesa_b2c_transactions_request_id_key UNIQUE (request_id);


--
-- Name: mpesa_c2b_transactions mpesa_c2b_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_c2b_transactions
    ADD CONSTRAINT mpesa_c2b_transactions_pkey PRIMARY KEY (id);


--
-- Name: mpesa_c2b_transactions mpesa_c2b_transactions_trans_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_c2b_transactions
    ADD CONSTRAINT mpesa_c2b_transactions_trans_id_key UNIQUE (trans_id);


--
-- Name: mpesa_config mpesa_config_config_key_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_config
    ADD CONSTRAINT mpesa_config_config_key_key UNIQUE (config_key);


--
-- Name: mpesa_config mpesa_config_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_config
    ADD CONSTRAINT mpesa_config_pkey PRIMARY KEY (id);


--
-- Name: mpesa_transactions mpesa_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_transactions
    ADD CONSTRAINT mpesa_transactions_pkey PRIMARY KEY (id);


--
-- Name: network_devices network_devices_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.network_devices
    ADD CONSTRAINT network_devices_pkey PRIMARY KEY (id);


--
-- Name: onu_discovery_log onu_discovery_log_olt_id_serial_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.onu_discovery_log
    ADD CONSTRAINT onu_discovery_log_olt_id_serial_number_key UNIQUE (olt_id, serial_number);


--
-- Name: onu_discovery_log onu_discovery_log_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.onu_discovery_log
    ADD CONSTRAINT onu_discovery_log_pkey PRIMARY KEY (id);


--
-- Name: onu_signal_history onu_signal_history_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.onu_signal_history
    ADD CONSTRAINT onu_signal_history_pkey PRIMARY KEY (id);


--
-- Name: onu_uptime_log onu_uptime_log_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.onu_uptime_log
    ADD CONSTRAINT onu_uptime_log_pkey PRIMARY KEY (id);


--
-- Name: orders orders_order_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_order_number_key UNIQUE (order_number);


--
-- Name: orders orders_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_pkey PRIMARY KEY (id);


--
-- Name: payroll_commissions payroll_commissions_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.payroll_commissions
    ADD CONSTRAINT payroll_commissions_pkey PRIMARY KEY (id);


--
-- Name: payroll payroll_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.payroll
    ADD CONSTRAINT payroll_pkey PRIMARY KEY (id);


--
-- Name: performance_reviews performance_reviews_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.performance_reviews
    ADD CONSTRAINT performance_reviews_pkey PRIMARY KEY (id);


--
-- Name: permissions permissions_name_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_key UNIQUE (name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: products_services products_services_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.products_services
    ADD CONSTRAINT products_services_pkey PRIMARY KEY (id);


--
-- Name: public_holidays public_holidays_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.public_holidays
    ADD CONSTRAINT public_holidays_pkey PRIMARY KEY (id);


--
-- Name: purchase_order_items purchase_order_items_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_pkey PRIMARY KEY (id);


--
-- Name: purchase_orders purchase_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_pkey PRIMARY KEY (id);


--
-- Name: purchase_orders purchase_orders_po_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_po_number_key UNIQUE (po_number);


--
-- Name: quote_items quote_items_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.quote_items
    ADD CONSTRAINT quote_items_pkey PRIMARY KEY (id);


--
-- Name: quotes quotes_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.quotes
    ADD CONSTRAINT quotes_pkey PRIMARY KEY (id);


--
-- Name: quotes quotes_quote_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.quotes
    ADD CONSTRAINT quotes_quote_number_key UNIQUE (quote_number);


--
-- Name: role_permissions role_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.role_permissions
    ADD CONSTRAINT role_permissions_pkey PRIMARY KEY (id);


--
-- Name: role_permissions role_permissions_role_id_permission_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.role_permissions
    ADD CONSTRAINT role_permissions_role_id_permission_id_key UNIQUE (role_id, permission_id);


--
-- Name: roles roles_name_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_key UNIQUE (name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: salary_advance_repayments salary_advance_repayments_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.salary_advance_repayments
    ADD CONSTRAINT salary_advance_repayments_pkey PRIMARY KEY (id);


--
-- Name: salary_advances salary_advances_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.salary_advances
    ADD CONSTRAINT salary_advances_pkey PRIMARY KEY (id);


--
-- Name: sales_commissions sales_commissions_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.sales_commissions
    ADD CONSTRAINT sales_commissions_pkey PRIMARY KEY (id);


--
-- Name: salespersons salespersons_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.salespersons
    ADD CONSTRAINT salespersons_pkey PRIMARY KEY (id);


--
-- Name: schema_migrations schema_migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.schema_migrations
    ADD CONSTRAINT schema_migrations_pkey PRIMARY KEY (id);


--
-- Name: service_fee_types service_fee_types_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.service_fee_types
    ADD CONSTRAINT service_fee_types_pkey PRIMARY KEY (id);


--
-- Name: service_packages service_packages_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.service_packages
    ADD CONSTRAINT service_packages_pkey PRIMARY KEY (id);


--
-- Name: service_packages service_packages_slug_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.service_packages
    ADD CONSTRAINT service_packages_slug_key UNIQUE (slug);


--
-- Name: settings settings_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_pkey PRIMARY KEY (id);


--
-- Name: settings settings_setting_key_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_setting_key_key UNIQUE (setting_key);


--
-- Name: sla_business_hours sla_business_hours_day_of_week_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.sla_business_hours
    ADD CONSTRAINT sla_business_hours_day_of_week_key UNIQUE (day_of_week);


--
-- Name: sla_business_hours sla_business_hours_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.sla_business_hours
    ADD CONSTRAINT sla_business_hours_pkey PRIMARY KEY (id);


--
-- Name: sla_holidays sla_holidays_holiday_date_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.sla_holidays
    ADD CONSTRAINT sla_holidays_holiday_date_key UNIQUE (holiday_date);


--
-- Name: sla_holidays sla_holidays_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.sla_holidays
    ADD CONSTRAINT sla_holidays_pkey PRIMARY KEY (id);


--
-- Name: sla_policies sla_policies_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.sla_policies
    ADD CONSTRAINT sla_policies_pkey PRIMARY KEY (id);


--
-- Name: sms_logs sms_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.sms_logs
    ADD CONSTRAINT sms_logs_pkey PRIMARY KEY (id);


--
-- Name: tax_rates tax_rates_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.tax_rates
    ADD CONSTRAINT tax_rates_pkey PRIMARY KEY (id);


--
-- Name: team_members team_members_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.team_members
    ADD CONSTRAINT team_members_pkey PRIMARY KEY (id);


--
-- Name: team_members team_members_team_id_employee_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.team_members
    ADD CONSTRAINT team_members_team_id_employee_id_key UNIQUE (team_id, employee_id);


--
-- Name: teams teams_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT teams_pkey PRIMARY KEY (id);


--
-- Name: technician_kit_items technician_kit_items_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.technician_kit_items
    ADD CONSTRAINT technician_kit_items_pkey PRIMARY KEY (id);


--
-- Name: technician_kits technician_kits_kit_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.technician_kits
    ADD CONSTRAINT technician_kits_kit_number_key UNIQUE (kit_number);


--
-- Name: technician_kits technician_kits_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.technician_kits
    ADD CONSTRAINT technician_kits_pkey PRIMARY KEY (id);


--
-- Name: ticket_categories ticket_categories_key_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_categories
    ADD CONSTRAINT ticket_categories_key_key UNIQUE (key);


--
-- Name: ticket_categories ticket_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_categories
    ADD CONSTRAINT ticket_categories_pkey PRIMARY KEY (id);


--
-- Name: ticket_comments ticket_comments_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_comments
    ADD CONSTRAINT ticket_comments_pkey PRIMARY KEY (id);


--
-- Name: ticket_commission_rates ticket_commission_rates_category_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_commission_rates
    ADD CONSTRAINT ticket_commission_rates_category_key UNIQUE (category);


--
-- Name: ticket_commission_rates ticket_commission_rates_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_commission_rates
    ADD CONSTRAINT ticket_commission_rates_pkey PRIMARY KEY (id);


--
-- Name: ticket_earnings ticket_earnings_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_earnings
    ADD CONSTRAINT ticket_earnings_pkey PRIMARY KEY (id);


--
-- Name: ticket_escalations ticket_escalations_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_escalations
    ADD CONSTRAINT ticket_escalations_pkey PRIMARY KEY (id);


--
-- Name: ticket_satisfaction_ratings ticket_satisfaction_ratings_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_satisfaction_ratings
    ADD CONSTRAINT ticket_satisfaction_ratings_pkey PRIMARY KEY (id);


--
-- Name: ticket_satisfaction_ratings ticket_satisfaction_ratings_ticket_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_satisfaction_ratings
    ADD CONSTRAINT ticket_satisfaction_ratings_ticket_id_key UNIQUE (ticket_id);


--
-- Name: ticket_service_fees ticket_service_fees_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_service_fees
    ADD CONSTRAINT ticket_service_fees_pkey PRIMARY KEY (id);


--
-- Name: ticket_sla_logs ticket_sla_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_sla_logs
    ADD CONSTRAINT ticket_sla_logs_pkey PRIMARY KEY (id);


--
-- Name: ticket_status_tokens ticket_status_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_status_tokens
    ADD CONSTRAINT ticket_status_tokens_pkey PRIMARY KEY (id);


--
-- Name: ticket_templates ticket_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_templates
    ADD CONSTRAINT ticket_templates_pkey PRIMARY KEY (id);


--
-- Name: tickets tickets_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_pkey PRIMARY KEY (id);


--
-- Name: tickets tickets_ticket_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_ticket_number_key UNIQUE (ticket_number);


--
-- Name: tr069_devices tr069_devices_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.tr069_devices
    ADD CONSTRAINT tr069_devices_pkey PRIMARY KEY (id);


--
-- Name: tr069_devices tr069_devices_serial_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.tr069_devices
    ADD CONSTRAINT tr069_devices_serial_number_key UNIQUE (serial_number);


--
-- Name: user_notifications user_notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.user_notifications
    ADD CONSTRAINT user_notifications_pkey PRIMARY KEY (id);


--
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: vendor_bill_items vendor_bill_items_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendor_bill_items
    ADD CONSTRAINT vendor_bill_items_pkey PRIMARY KEY (id);


--
-- Name: vendor_bills vendor_bills_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendor_bills
    ADD CONSTRAINT vendor_bills_pkey PRIMARY KEY (id);


--
-- Name: vendor_payments vendor_payments_payment_number_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendor_payments
    ADD CONSTRAINT vendor_payments_payment_number_key UNIQUE (payment_number);


--
-- Name: vendor_payments vendor_payments_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendor_payments
    ADD CONSTRAINT vendor_payments_pkey PRIMARY KEY (id);


--
-- Name: vendors vendors_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendors
    ADD CONSTRAINT vendors_pkey PRIMARY KEY (id);


--
-- Name: vlan_history vlan_history_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vlan_history
    ADD CONSTRAINT vlan_history_pkey PRIMARY KEY (id);


--
-- Name: whatsapp_conversations whatsapp_conversations_chat_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.whatsapp_conversations
    ADD CONSTRAINT whatsapp_conversations_chat_id_key UNIQUE (chat_id);


--
-- Name: whatsapp_conversations whatsapp_conversations_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.whatsapp_conversations
    ADD CONSTRAINT whatsapp_conversations_pkey PRIMARY KEY (id);


--
-- Name: whatsapp_logs whatsapp_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.whatsapp_logs
    ADD CONSTRAINT whatsapp_logs_pkey PRIMARY KEY (id);


--
-- Name: whatsapp_messages whatsapp_messages_message_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.whatsapp_messages
    ADD CONSTRAINT whatsapp_messages_message_id_key UNIQUE (message_id);


--
-- Name: whatsapp_messages whatsapp_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.whatsapp_messages
    ADD CONSTRAINT whatsapp_messages_pkey PRIMARY KEY (id);


--
-- Name: wireguard_peers wireguard_peers_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.wireguard_peers
    ADD CONSTRAINT wireguard_peers_pkey PRIMARY KEY (id);


--
-- Name: wireguard_servers wireguard_servers_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.wireguard_servers
    ADD CONSTRAINT wireguard_servers_pkey PRIMARY KEY (id);


--
-- Name: wireguard_settings wireguard_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.wireguard_settings
    ADD CONSTRAINT wireguard_settings_pkey PRIMARY KEY (id);


--
-- Name: wireguard_settings wireguard_settings_setting_key_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.wireguard_settings
    ADD CONSTRAINT wireguard_settings_setting_key_key UNIQUE (setting_key);


--
-- Name: wireguard_subnets wireguard_subnets_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.wireguard_subnets
    ADD CONSTRAINT wireguard_subnets_pkey PRIMARY KEY (id);


--
-- Name: wireguard_sync_logs wireguard_sync_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.wireguard_sync_logs
    ADD CONSTRAINT wireguard_sync_logs_pkey PRIMARY KEY (id);


--
-- Name: idx_activity_logs_action; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_activity_logs_action ON public.activity_logs USING btree (action_type);


--
-- Name: idx_activity_logs_created; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_activity_logs_created ON public.activity_logs USING btree (created_at);


--
-- Name: idx_activity_logs_entity; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_activity_logs_entity ON public.activity_logs USING btree (entity_type);


--
-- Name: idx_activity_logs_user; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_activity_logs_user ON public.activity_logs USING btree (user_id);


--
-- Name: idx_announcement_recipients_announcement; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_announcement_recipients_announcement ON public.announcement_recipients USING btree (announcement_id);


--
-- Name: idx_announcement_recipients_employee; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_announcement_recipients_employee ON public.announcement_recipients USING btree (employee_id);


--
-- Name: idx_announcements_status; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_announcements_status ON public.announcements USING btree (status);


--
-- Name: idx_attendance_date; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_attendance_date ON public.attendance USING btree (date);


--
-- Name: idx_attendance_employee; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_attendance_employee ON public.attendance USING btree (employee_id);


--
-- Name: idx_attendance_employee_date; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_attendance_employee_date ON public.attendance USING btree (employee_id, date);


--
-- Name: idx_attendance_notification_logs_employee_date; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_attendance_notification_logs_employee_date ON public.attendance_notification_logs USING btree (employee_id, attendance_date);


--
-- Name: idx_b2b_created; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_b2b_created ON public.mpesa_b2b_transactions USING btree (created_at DESC);


--
-- Name: idx_b2b_status; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_b2b_status ON public.mpesa_b2b_transactions USING btree (status);


--
-- Name: idx_b2c_created; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_b2c_created ON public.mpesa_b2c_transactions USING btree (created_at DESC);


--
-- Name: idx_b2c_linked; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_b2c_linked ON public.mpesa_b2c_transactions USING btree (linked_type, linked_id);


--
-- Name: idx_b2c_phone; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_b2c_phone ON public.mpesa_b2c_transactions USING btree (phone);


--
-- Name: idx_b2c_purpose; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_b2c_purpose ON public.mpesa_b2c_transactions USING btree (purpose);


--
-- Name: idx_b2c_status; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_b2c_status ON public.mpesa_b2c_transactions USING btree (status);


--
-- Name: idx_bill_reminders_bill; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_bill_reminders_bill ON public.bill_reminders USING btree (bill_id);


--
-- Name: idx_bill_reminders_date; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_bill_reminders_date ON public.bill_reminders USING btree (reminder_date);


--
-- Name: idx_branches_active; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_branches_active ON public.branches USING btree (is_active);


--
-- Name: idx_customer_payments_invoice; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_customer_payments_invoice ON public.customer_payments USING btree (invoice_id);


--
-- Name: idx_customer_ticket_tokens_lookup; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_customer_ticket_tokens_lookup ON public.customer_ticket_tokens USING btree (token_lookup);


--
-- Name: idx_customer_ticket_tokens_ticket; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_customer_ticket_tokens_ticket ON public.customer_ticket_tokens USING btree (ticket_id);


--
-- Name: idx_customers_account; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_customers_account ON public.customers USING btree (account_number);


--
-- Name: idx_device_onus_customer; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_device_onus_customer ON public.device_onus USING btree (customer_id);


--
-- Name: idx_device_onus_status; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_device_onus_status ON public.device_onus USING btree (status);


--
-- Name: idx_device_vlans_device; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_device_vlans_device ON public.device_vlans USING btree (device_id);


--
-- Name: idx_employee_branches_branch; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_employee_branches_branch ON public.employee_branches USING btree (branch_id);


--
-- Name: idx_employee_branches_employee; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_employee_branches_employee ON public.employee_branches USING btree (employee_id);


--
-- Name: idx_employees_department; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_employees_department ON public.employees USING btree (department_id);


--
-- Name: idx_employees_status; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_employees_status ON public.employees USING btree (employment_status);


--
-- Name: idx_expenses_category; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_expenses_category ON public.expenses USING btree (category_id);


--
-- Name: idx_huawei_apartments_subzone; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_huawei_apartments_subzone ON public.huawei_apartments USING btree (subzone_id);


--
-- Name: idx_huawei_apartments_zone; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_huawei_apartments_zone ON public.huawei_apartments USING btree (zone_id);


--
-- Name: idx_huawei_odb_units_apartment; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_huawei_odb_units_apartment ON public.huawei_odb_units USING btree (apartment_id);


--
-- Name: idx_huawei_odb_units_zone; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_huawei_odb_units_zone ON public.huawei_odb_units USING btree (zone_id);


--
-- Name: idx_huawei_odb_zone; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_huawei_odb_zone ON public.huawei_odb_units USING btree (zone_id);


--
-- Name: idx_huawei_onus_apartment; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_huawei_onus_apartment ON public.huawei_onus USING btree (apartment_id);


--
-- Name: idx_huawei_onus_area; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_huawei_onus_area ON public.huawei_onus USING btree (area);


--
-- Name: idx_huawei_onus_line_profile; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_huawei_onus_line_profile ON public.huawei_onus USING btree (line_profile_id);


--
-- Name: idx_huawei_onus_odb; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_huawei_onus_odb ON public.huawei_onus USING btree (odb_id);


--
-- Name: idx_huawei_onus_srv_profile; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_huawei_onus_srv_profile ON public.huawei_onus USING btree (srv_profile_id);


--
-- Name: idx_huawei_onus_vlan; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_huawei_onus_vlan ON public.huawei_onus USING btree (vlan_id);


--
-- Name: idx_huawei_onus_zone; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_huawei_onus_zone ON public.huawei_onus USING btree (zone);


--
-- Name: idx_huawei_subzones_zone; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_huawei_subzones_zone ON public.huawei_subzones USING btree (zone_id);


--
-- Name: idx_huawei_vlans_active; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_huawei_vlans_active ON public.huawei_vlans USING btree (is_active);


--
-- Name: idx_huawei_vlans_olt; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_huawei_vlans_olt ON public.huawei_vlans USING btree (olt_id);


--
-- Name: idx_huawei_vlans_vlan_id; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_huawei_vlans_vlan_id ON public.huawei_vlans USING btree (vlan_id);


--
-- Name: idx_interface_history_time; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_interface_history_time ON public.interface_history USING btree (interface_id, recorded_at);


--
-- Name: idx_invoices_customer; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_invoices_customer ON public.invoices USING btree (customer_id);


--
-- Name: idx_invoices_due_date; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_invoices_due_date ON public.invoices USING btree (due_date);


--
-- Name: idx_invoices_status; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_invoices_status ON public.invoices USING btree (status);


--
-- Name: idx_mobile_notifications_user; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_mobile_notifications_user ON public.mobile_notifications USING btree (user_id, is_read);


--
-- Name: idx_mobile_tokens_expires; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_mobile_tokens_expires ON public.mobile_tokens USING btree (expires_at);


--
-- Name: idx_mobile_tokens_token; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_mobile_tokens_token ON public.mobile_tokens USING btree (token);


--
-- Name: idx_monitoring_log_device; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_monitoring_log_device ON public.device_monitoring_log USING btree (device_id, recorded_at);


--
-- Name: idx_olt_boards_olt; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_olt_boards_olt ON public.huawei_olt_boards USING btree (olt_id);


--
-- Name: idx_olt_pon_ports_olt; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_olt_pon_ports_olt ON public.huawei_olt_pon_ports USING btree (olt_id);


--
-- Name: idx_olt_uplinks_olt; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_olt_uplinks_olt ON public.huawei_olt_uplinks USING btree (olt_id);


--
-- Name: idx_olt_vlans_olt; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_olt_vlans_olt ON public.huawei_olt_vlans USING btree (olt_id);


--
-- Name: idx_payroll_employee; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_payroll_employee ON public.payroll USING btree (employee_id);


--
-- Name: idx_payroll_period; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_payroll_period ON public.payroll USING btree (pay_period_start, pay_period_end);


--
-- Name: idx_performance_employee; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_performance_employee ON public.performance_reviews USING btree (employee_id);


--
-- Name: idx_port_vlans_olt; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_port_vlans_olt ON public.huawei_port_vlans USING btree (olt_id);


--
-- Name: idx_port_vlans_port; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_port_vlans_port ON public.huawei_port_vlans USING btree (port_name);


--
-- Name: idx_quotes_customer; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_quotes_customer ON public.quotes USING btree (customer_id);


--
-- Name: idx_service_packages_active; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_service_packages_active ON public.service_packages USING btree (is_active);


--
-- Name: idx_service_packages_order; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_service_packages_order ON public.service_packages USING btree (display_order);


--
-- Name: idx_service_templates_name; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_service_templates_name ON public.huawei_service_templates USING btree (name);


--
-- Name: idx_signal_history_onu_time; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_signal_history_onu_time ON public.onu_signal_history USING btree (onu_id, recorded_at DESC);


--
-- Name: idx_team_members_employee_id; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_team_members_employee_id ON public.team_members USING btree (employee_id);


--
-- Name: idx_team_members_team_id; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_team_members_team_id ON public.team_members USING btree (team_id);


--
-- Name: idx_teams_branch; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_teams_branch ON public.teams USING btree (branch_id);


--
-- Name: idx_ticket_service_fees_ticket; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_ticket_service_fees_ticket ON public.ticket_service_fees USING btree (ticket_id);


--
-- Name: idx_ticket_status_tokens_expires; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_ticket_status_tokens_expires ON public.ticket_status_tokens USING btree (expires_at);


--
-- Name: idx_ticket_status_tokens_hash; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_ticket_status_tokens_hash ON public.ticket_status_tokens USING btree (token_hash);


--
-- Name: idx_ticket_status_tokens_lookup; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_ticket_status_tokens_lookup ON public.ticket_status_tokens USING btree (token_lookup);


--
-- Name: idx_ticket_status_tokens_ticket; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_ticket_status_tokens_ticket ON public.ticket_status_tokens USING btree (ticket_id);


--
-- Name: idx_ticket_templates_category; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_ticket_templates_category ON public.ticket_templates USING btree (category);


--
-- Name: idx_tickets_assigned; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_tickets_assigned ON public.tickets USING btree (assigned_to);


--
-- Name: idx_tickets_branch; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_tickets_branch ON public.tickets USING btree (branch_id);


--
-- Name: idx_tickets_created_at; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_tickets_created_at ON public.tickets USING btree (created_at);


--
-- Name: idx_tickets_customer; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_tickets_customer ON public.tickets USING btree (customer_id);


--
-- Name: idx_tickets_status; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_tickets_status ON public.tickets USING btree (status);


--
-- Name: idx_tickets_team_id; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_tickets_team_id ON public.tickets USING btree (team_id);


--
-- Name: idx_uptime_log_onu; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_uptime_log_onu ON public.onu_uptime_log USING btree (onu_id, started_at DESC);


--
-- Name: idx_vendor_bills_status; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_vendor_bills_status ON public.vendor_bills USING btree (status);


--
-- Name: idx_vendor_bills_vendor; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_vendor_bills_vendor ON public.vendor_bills USING btree (vendor_id);


--
-- Name: idx_vendor_payments_bill; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_vendor_payments_bill ON public.vendor_payments USING btree (bill_id);


--
-- Name: idx_vlan_history_time; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_vlan_history_time ON public.vlan_history USING btree (vlan_record_id, recorded_at);


--
-- Name: idx_whatsapp_logs_sent; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_whatsapp_logs_sent ON public.whatsapp_logs USING btree (sent_at DESC);


--
-- Name: idx_whatsapp_logs_ticket; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_whatsapp_logs_ticket ON public.whatsapp_logs USING btree (ticket_id);


--
-- Name: idx_wireguard_subnets_peer; Type: INDEX; Schema: public; Owner: neondb_owner
--

CREATE INDEX idx_wireguard_subnets_peer ON public.wireguard_subnets USING btree (vpn_peer_id);


--
-- Name: activity_logs activity_logs_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT activity_logs_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: announcement_recipients announcement_recipients_announcement_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.announcement_recipients
    ADD CONSTRAINT announcement_recipients_announcement_id_fkey FOREIGN KEY (announcement_id) REFERENCES public.announcements(id) ON DELETE CASCADE;


--
-- Name: announcement_recipients announcement_recipients_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.announcement_recipients
    ADD CONSTRAINT announcement_recipients_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: announcements announcements_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.announcements
    ADD CONSTRAINT announcements_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: announcements announcements_target_branch_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.announcements
    ADD CONSTRAINT announcements_target_branch_id_fkey FOREIGN KEY (target_branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- Name: announcements announcements_target_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.announcements
    ADD CONSTRAINT announcements_target_team_id_fkey FOREIGN KEY (target_team_id) REFERENCES public.teams(id) ON DELETE SET NULL;


--
-- Name: attendance attendance_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.attendance
    ADD CONSTRAINT attendance_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: attendance_notification_logs attendance_notification_logs_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.attendance_notification_logs
    ADD CONSTRAINT attendance_notification_logs_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id);


--
-- Name: attendance_notification_logs attendance_notification_logs_notification_template_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.attendance_notification_logs
    ADD CONSTRAINT attendance_notification_logs_notification_template_id_fkey FOREIGN KEY (notification_template_id) REFERENCES public.hr_notification_templates(id);


--
-- Name: bill_reminders bill_reminders_bill_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.bill_reminders
    ADD CONSTRAINT bill_reminders_bill_id_fkey FOREIGN KEY (bill_id) REFERENCES public.vendor_bills(id) ON DELETE CASCADE;


--
-- Name: bill_reminders bill_reminders_sent_to_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.bill_reminders
    ADD CONSTRAINT bill_reminders_sent_to_fkey FOREIGN KEY (sent_to) REFERENCES public.users(id);


--
-- Name: branch_employees branch_employees_branch_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.branch_employees
    ADD CONSTRAINT branch_employees_branch_id_fkey FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: branch_employees branch_employees_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.branch_employees
    ADD CONSTRAINT branch_employees_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: branches branches_manager_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_manager_id_fkey FOREIGN KEY (manager_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: chart_of_accounts chart_of_accounts_parent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.chart_of_accounts
    ADD CONSTRAINT chart_of_accounts_parent_id_fkey FOREIGN KEY (parent_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: complaints complaints_converted_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.complaints
    ADD CONSTRAINT complaints_converted_ticket_id_fkey FOREIGN KEY (converted_ticket_id) REFERENCES public.tickets(id) ON DELETE SET NULL;


--
-- Name: complaints complaints_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.complaints
    ADD CONSTRAINT complaints_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: complaints complaints_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.complaints
    ADD CONSTRAINT complaints_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: complaints complaints_reviewed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.complaints
    ADD CONSTRAINT complaints_reviewed_by_fkey FOREIGN KEY (reviewed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: customer_payments customer_payments_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.customer_payments
    ADD CONSTRAINT customer_payments_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: customer_payments customer_payments_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.customer_payments
    ADD CONSTRAINT customer_payments_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: customer_payments customer_payments_invoice_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.customer_payments
    ADD CONSTRAINT customer_payments_invoice_id_fkey FOREIGN KEY (invoice_id) REFERENCES public.invoices(id) ON DELETE SET NULL;


--
-- Name: customer_payments customer_payments_mpesa_transaction_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.customer_payments
    ADD CONSTRAINT customer_payments_mpesa_transaction_id_fkey FOREIGN KEY (mpesa_transaction_id) REFERENCES public.mpesa_transactions(id);


--
-- Name: customer_ticket_tokens customer_ticket_tokens_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.customer_ticket_tokens
    ADD CONSTRAINT customer_ticket_tokens_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: customer_ticket_tokens customer_ticket_tokens_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.customer_ticket_tokens
    ADD CONSTRAINT customer_ticket_tokens_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;


--
-- Name: customers customers_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: device_interfaces device_interfaces_device_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_interfaces
    ADD CONSTRAINT device_interfaces_device_id_fkey FOREIGN KEY (device_id) REFERENCES public.network_devices(id) ON DELETE CASCADE;


--
-- Name: device_monitoring_log device_monitoring_log_device_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_monitoring_log
    ADD CONSTRAINT device_monitoring_log_device_id_fkey FOREIGN KEY (device_id) REFERENCES public.network_devices(id) ON DELETE CASCADE;


--
-- Name: device_onus device_onus_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_onus
    ADD CONSTRAINT device_onus_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: device_onus device_onus_device_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_onus
    ADD CONSTRAINT device_onus_device_id_fkey FOREIGN KEY (device_id) REFERENCES public.network_devices(id) ON DELETE CASCADE;


--
-- Name: device_vlans device_vlans_device_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.device_vlans
    ADD CONSTRAINT device_vlans_device_id_fkey FOREIGN KEY (device_id) REFERENCES public.network_devices(id) ON DELETE CASCADE;


--
-- Name: employee_branches employee_branches_assigned_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.employee_branches
    ADD CONSTRAINT employee_branches_assigned_by_fkey FOREIGN KEY (assigned_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: employee_branches employee_branches_branch_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.employee_branches
    ADD CONSTRAINT employee_branches_branch_id_fkey FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE CASCADE;


--
-- Name: employee_branches employee_branches_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.employee_branches
    ADD CONSTRAINT employee_branches_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: employees employees_department_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_department_id_fkey FOREIGN KEY (department_id) REFERENCES public.departments(id) ON DELETE SET NULL;


--
-- Name: employees employees_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: equipment_assignments equipment_assignments_assigned_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_assignments
    ADD CONSTRAINT equipment_assignments_assigned_by_fkey FOREIGN KEY (assigned_by) REFERENCES public.users(id);


--
-- Name: equipment_assignments equipment_assignments_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_assignments
    ADD CONSTRAINT equipment_assignments_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: equipment_assignments equipment_assignments_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_assignments
    ADD CONSTRAINT equipment_assignments_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: equipment_assignments equipment_assignments_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_assignments
    ADD CONSTRAINT equipment_assignments_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: equipment_categories equipment_categories_parent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_categories
    ADD CONSTRAINT equipment_categories_parent_id_fkey FOREIGN KEY (parent_id) REFERENCES public.equipment_categories(id) ON DELETE SET NULL;


--
-- Name: equipment equipment_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.equipment_categories(id) ON DELETE SET NULL;


--
-- Name: equipment_faults equipment_faults_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_faults
    ADD CONSTRAINT equipment_faults_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: equipment_faults equipment_faults_reported_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_faults
    ADD CONSTRAINT equipment_faults_reported_by_fkey FOREIGN KEY (reported_by) REFERENCES public.users(id);


--
-- Name: equipment equipment_installed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_installed_by_fkey FOREIGN KEY (installed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: equipment equipment_installed_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_installed_customer_id_fkey FOREIGN KEY (installed_customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: equipment_lifecycle_logs equipment_lifecycle_logs_changed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_lifecycle_logs
    ADD CONSTRAINT equipment_lifecycle_logs_changed_by_fkey FOREIGN KEY (changed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: equipment_lifecycle_logs equipment_lifecycle_logs_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_lifecycle_logs
    ADD CONSTRAINT equipment_lifecycle_logs_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: equipment_loans equipment_loans_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_loans
    ADD CONSTRAINT equipment_loans_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE CASCADE;


--
-- Name: equipment_loans equipment_loans_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_loans
    ADD CONSTRAINT equipment_loans_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: equipment_loans equipment_loans_loaned_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment_loans
    ADD CONSTRAINT equipment_loans_loaned_by_fkey FOREIGN KEY (loaned_by) REFERENCES public.users(id);


--
-- Name: equipment equipment_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_location_id_fkey FOREIGN KEY (location_id) REFERENCES public.inventory_locations(id) ON DELETE SET NULL;


--
-- Name: equipment equipment_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_warehouse_id_fkey FOREIGN KEY (warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE SET NULL;


--
-- Name: expense_categories expense_categories_account_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.expense_categories
    ADD CONSTRAINT expense_categories_account_id_fkey FOREIGN KEY (account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: expenses expenses_approved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_approved_by_fkey FOREIGN KEY (approved_by) REFERENCES public.users(id);


--
-- Name: expenses expenses_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.expense_categories(id);


--
-- Name: expenses expenses_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: expenses expenses_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id);


--
-- Name: expenses expenses_vendor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_vendor_id_fkey FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: departments fk_manager; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT fk_manager FOREIGN KEY (manager_id) REFERENCES public.employees(id) ON DELETE SET NULL;


--
-- Name: huawei_alerts huawei_alerts_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_alerts
    ADD CONSTRAINT huawei_alerts_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;


--
-- Name: huawei_alerts huawei_alerts_onu_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_alerts
    ADD CONSTRAINT huawei_alerts_onu_id_fkey FOREIGN KEY (onu_id) REFERENCES public.huawei_onus(id) ON DELETE CASCADE;


--
-- Name: huawei_apartments huawei_apartments_subzone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_apartments
    ADD CONSTRAINT huawei_apartments_subzone_id_fkey FOREIGN KEY (subzone_id) REFERENCES public.huawei_subzones(id) ON DELETE SET NULL;


--
-- Name: huawei_apartments huawei_apartments_zone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_apartments
    ADD CONSTRAINT huawei_apartments_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES public.huawei_zones(id) ON DELETE CASCADE;


--
-- Name: huawei_odb_units huawei_odb_units_apartment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_odb_units
    ADD CONSTRAINT huawei_odb_units_apartment_id_fkey FOREIGN KEY (apartment_id) REFERENCES public.huawei_apartments(id) ON DELETE SET NULL;


--
-- Name: huawei_odb_units huawei_odb_units_subzone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_odb_units
    ADD CONSTRAINT huawei_odb_units_subzone_id_fkey FOREIGN KEY (subzone_id) REFERENCES public.huawei_subzones(id) ON DELETE SET NULL;


--
-- Name: huawei_odb_units huawei_odb_units_zone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_odb_units
    ADD CONSTRAINT huawei_odb_units_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES public.huawei_zones(id) ON DELETE CASCADE;


--
-- Name: huawei_olt_boards huawei_olt_boards_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olt_boards
    ADD CONSTRAINT huawei_olt_boards_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;


--
-- Name: huawei_olt_pon_ports huawei_olt_pon_ports_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olt_pon_ports
    ADD CONSTRAINT huawei_olt_pon_ports_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;


--
-- Name: huawei_olt_uplinks huawei_olt_uplinks_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olt_uplinks
    ADD CONSTRAINT huawei_olt_uplinks_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;


--
-- Name: huawei_olt_vlans huawei_olt_vlans_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olt_vlans
    ADD CONSTRAINT huawei_olt_vlans_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;


--
-- Name: huawei_olts huawei_olts_branch_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_olts
    ADD CONSTRAINT huawei_olts_branch_id_fkey FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- Name: huawei_onu_mgmt_ips huawei_onu_mgmt_ips_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_onu_mgmt_ips
    ADD CONSTRAINT huawei_onu_mgmt_ips_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;


--
-- Name: huawei_onu_mgmt_ips huawei_onu_mgmt_ips_onu_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_onu_mgmt_ips
    ADD CONSTRAINT huawei_onu_mgmt_ips_onu_id_fkey FOREIGN KEY (onu_id) REFERENCES public.huawei_onus(id) ON DELETE SET NULL;


--
-- Name: huawei_onus huawei_onus_apartment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_onus
    ADD CONSTRAINT huawei_onus_apartment_id_fkey FOREIGN KEY (apartment_id) REFERENCES public.huawei_apartments(id) ON DELETE SET NULL;


--
-- Name: huawei_onus huawei_onus_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_onus
    ADD CONSTRAINT huawei_onus_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: huawei_onus huawei_onus_odb_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_onus
    ADD CONSTRAINT huawei_onus_odb_id_fkey FOREIGN KEY (odb_id) REFERENCES public.huawei_odb_units(id) ON DELETE SET NULL;


--
-- Name: huawei_onus huawei_onus_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_onus
    ADD CONSTRAINT huawei_onus_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;


--
-- Name: huawei_onus huawei_onus_onu_type_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_onus
    ADD CONSTRAINT huawei_onus_onu_type_id_fkey FOREIGN KEY (onu_type_id) REFERENCES public.huawei_onu_types(id);


--
-- Name: huawei_onus huawei_onus_service_profile_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_onus
    ADD CONSTRAINT huawei_onus_service_profile_id_fkey FOREIGN KEY (service_profile_id) REFERENCES public.huawei_service_profiles(id) ON DELETE SET NULL;


--
-- Name: huawei_onus huawei_onus_subzone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_onus
    ADD CONSTRAINT huawei_onus_subzone_id_fkey FOREIGN KEY (subzone_id) REFERENCES public.huawei_subzones(id) ON DELETE SET NULL;


--
-- Name: huawei_onus huawei_onus_zone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_onus
    ADD CONSTRAINT huawei_onus_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES public.huawei_zones(id) ON DELETE SET NULL;


--
-- Name: huawei_port_vlans huawei_port_vlans_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_port_vlans
    ADD CONSTRAINT huawei_port_vlans_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;


--
-- Name: huawei_provisioning_logs huawei_provisioning_logs_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_provisioning_logs
    ADD CONSTRAINT huawei_provisioning_logs_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE SET NULL;


--
-- Name: huawei_provisioning_logs huawei_provisioning_logs_onu_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_provisioning_logs
    ADD CONSTRAINT huawei_provisioning_logs_onu_id_fkey FOREIGN KEY (onu_id) REFERENCES public.huawei_onus(id) ON DELETE SET NULL;


--
-- Name: huawei_subzones huawei_subzones_zone_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_subzones
    ADD CONSTRAINT huawei_subzones_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES public.huawei_zones(id) ON DELETE CASCADE;


--
-- Name: huawei_vlans huawei_vlans_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.huawei_vlans
    ADD CONSTRAINT huawei_vlans_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;


--
-- Name: interface_history interface_history_interface_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.interface_history
    ADD CONSTRAINT interface_history_interface_id_fkey FOREIGN KEY (interface_id) REFERENCES public.device_interfaces(id) ON DELETE CASCADE;


--
-- Name: inventory_audit_items inventory_audit_items_audit_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_audit_items
    ADD CONSTRAINT inventory_audit_items_audit_id_fkey FOREIGN KEY (audit_id) REFERENCES public.inventory_audits(id) ON DELETE CASCADE;


--
-- Name: inventory_audit_items inventory_audit_items_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_audit_items
    ADD CONSTRAINT inventory_audit_items_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.equipment_categories(id) ON DELETE SET NULL;


--
-- Name: inventory_audit_items inventory_audit_items_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_audit_items
    ADD CONSTRAINT inventory_audit_items_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;


--
-- Name: inventory_audit_items inventory_audit_items_verified_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_audit_items
    ADD CONSTRAINT inventory_audit_items_verified_by_fkey FOREIGN KEY (verified_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_audits inventory_audits_completed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_audits
    ADD CONSTRAINT inventory_audits_completed_by_fkey FOREIGN KEY (completed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_audits inventory_audits_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_audits
    ADD CONSTRAINT inventory_audits_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_audits inventory_audits_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_audits
    ADD CONSTRAINT inventory_audits_warehouse_id_fkey FOREIGN KEY (warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE SET NULL;


--
-- Name: inventory_locations inventory_locations_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_locations
    ADD CONSTRAINT inventory_locations_warehouse_id_fkey FOREIGN KEY (warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE CASCADE;


--
-- Name: inventory_loss_reports inventory_loss_reports_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_loss_reports
    ADD CONSTRAINT inventory_loss_reports_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE SET NULL;


--
-- Name: inventory_loss_reports inventory_loss_reports_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_loss_reports
    ADD CONSTRAINT inventory_loss_reports_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;


--
-- Name: inventory_loss_reports inventory_loss_reports_reported_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_loss_reports
    ADD CONSTRAINT inventory_loss_reports_reported_by_fkey FOREIGN KEY (reported_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_loss_reports inventory_loss_reports_resolved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_loss_reports
    ADD CONSTRAINT inventory_loss_reports_resolved_by_fkey FOREIGN KEY (resolved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_po_items inventory_po_items_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_po_items
    ADD CONSTRAINT inventory_po_items_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.equipment_categories(id) ON DELETE SET NULL;


--
-- Name: inventory_po_items inventory_po_items_po_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_po_items
    ADD CONSTRAINT inventory_po_items_po_id_fkey FOREIGN KEY (po_id) REFERENCES public.inventory_purchase_orders(id) ON DELETE CASCADE;


--
-- Name: inventory_purchase_orders inventory_purchase_orders_approved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_purchase_orders
    ADD CONSTRAINT inventory_purchase_orders_approved_by_fkey FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_purchase_orders inventory_purchase_orders_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_purchase_orders
    ADD CONSTRAINT inventory_purchase_orders_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_receipt_items inventory_receipt_items_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_receipt_items
    ADD CONSTRAINT inventory_receipt_items_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.equipment_categories(id) ON DELETE SET NULL;


--
-- Name: inventory_receipt_items inventory_receipt_items_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_receipt_items
    ADD CONSTRAINT inventory_receipt_items_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;


--
-- Name: inventory_receipt_items inventory_receipt_items_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_receipt_items
    ADD CONSTRAINT inventory_receipt_items_location_id_fkey FOREIGN KEY (location_id) REFERENCES public.inventory_locations(id) ON DELETE SET NULL;


--
-- Name: inventory_receipt_items inventory_receipt_items_po_item_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_receipt_items
    ADD CONSTRAINT inventory_receipt_items_po_item_id_fkey FOREIGN KEY (po_item_id) REFERENCES public.inventory_po_items(id) ON DELETE SET NULL;


--
-- Name: inventory_receipt_items inventory_receipt_items_receipt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_receipt_items
    ADD CONSTRAINT inventory_receipt_items_receipt_id_fkey FOREIGN KEY (receipt_id) REFERENCES public.inventory_receipts(id) ON DELETE CASCADE;


--
-- Name: inventory_receipts inventory_receipts_po_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_receipts
    ADD CONSTRAINT inventory_receipts_po_id_fkey FOREIGN KEY (po_id) REFERENCES public.inventory_purchase_orders(id) ON DELETE SET NULL;


--
-- Name: inventory_receipts inventory_receipts_received_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_receipts
    ADD CONSTRAINT inventory_receipts_received_by_fkey FOREIGN KEY (received_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_receipts inventory_receipts_verified_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_receipts
    ADD CONSTRAINT inventory_receipts_verified_by_fkey FOREIGN KEY (verified_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_receipts inventory_receipts_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_receipts
    ADD CONSTRAINT inventory_receipts_warehouse_id_fkey FOREIGN KEY (warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE SET NULL;


--
-- Name: inventory_return_items inventory_return_items_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_return_items
    ADD CONSTRAINT inventory_return_items_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;


--
-- Name: inventory_return_items inventory_return_items_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_return_items
    ADD CONSTRAINT inventory_return_items_location_id_fkey FOREIGN KEY (location_id) REFERENCES public.inventory_locations(id) ON DELETE SET NULL;


--
-- Name: inventory_return_items inventory_return_items_request_item_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_return_items
    ADD CONSTRAINT inventory_return_items_request_item_id_fkey FOREIGN KEY (request_item_id) REFERENCES public.inventory_stock_request_items(id) ON DELETE SET NULL;


--
-- Name: inventory_return_items inventory_return_items_return_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_return_items
    ADD CONSTRAINT inventory_return_items_return_id_fkey FOREIGN KEY (return_id) REFERENCES public.inventory_returns(id) ON DELETE CASCADE;


--
-- Name: inventory_returns inventory_returns_received_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_returns
    ADD CONSTRAINT inventory_returns_received_by_fkey FOREIGN KEY (received_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_returns inventory_returns_request_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_returns
    ADD CONSTRAINT inventory_returns_request_id_fkey FOREIGN KEY (request_id) REFERENCES public.inventory_stock_requests(id) ON DELETE SET NULL;


--
-- Name: inventory_returns inventory_returns_returned_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_returns
    ADD CONSTRAINT inventory_returns_returned_by_fkey FOREIGN KEY (returned_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_returns inventory_returns_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_returns
    ADD CONSTRAINT inventory_returns_warehouse_id_fkey FOREIGN KEY (warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE SET NULL;


--
-- Name: inventory_rma inventory_rma_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_rma
    ADD CONSTRAINT inventory_rma_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_rma inventory_rma_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_rma
    ADD CONSTRAINT inventory_rma_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: inventory_rma inventory_rma_fault_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_rma
    ADD CONSTRAINT inventory_rma_fault_id_fkey FOREIGN KEY (fault_id) REFERENCES public.equipment_faults(id) ON DELETE SET NULL;


--
-- Name: inventory_rma inventory_rma_replacement_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_rma
    ADD CONSTRAINT inventory_rma_replacement_equipment_id_fkey FOREIGN KEY (replacement_equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;


--
-- Name: inventory_stock_levels inventory_stock_levels_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_levels
    ADD CONSTRAINT inventory_stock_levels_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.equipment_categories(id) ON DELETE CASCADE;


--
-- Name: inventory_stock_levels inventory_stock_levels_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_levels
    ADD CONSTRAINT inventory_stock_levels_warehouse_id_fkey FOREIGN KEY (warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE CASCADE;


--
-- Name: inventory_stock_movements inventory_stock_movements_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_movements
    ADD CONSTRAINT inventory_stock_movements_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;


--
-- Name: inventory_stock_movements inventory_stock_movements_from_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_movements
    ADD CONSTRAINT inventory_stock_movements_from_location_id_fkey FOREIGN KEY (from_location_id) REFERENCES public.inventory_locations(id) ON DELETE SET NULL;


--
-- Name: inventory_stock_movements inventory_stock_movements_from_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_movements
    ADD CONSTRAINT inventory_stock_movements_from_warehouse_id_fkey FOREIGN KEY (from_warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE SET NULL;


--
-- Name: inventory_stock_movements inventory_stock_movements_performed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_movements
    ADD CONSTRAINT inventory_stock_movements_performed_by_fkey FOREIGN KEY (performed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_stock_movements inventory_stock_movements_to_location_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_movements
    ADD CONSTRAINT inventory_stock_movements_to_location_id_fkey FOREIGN KEY (to_location_id) REFERENCES public.inventory_locations(id) ON DELETE SET NULL;


--
-- Name: inventory_stock_movements inventory_stock_movements_to_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_movements
    ADD CONSTRAINT inventory_stock_movements_to_warehouse_id_fkey FOREIGN KEY (to_warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE SET NULL;


--
-- Name: inventory_stock_request_items inventory_stock_request_items_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_request_items
    ADD CONSTRAINT inventory_stock_request_items_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.equipment_categories(id) ON DELETE SET NULL;


--
-- Name: inventory_stock_request_items inventory_stock_request_items_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_request_items
    ADD CONSTRAINT inventory_stock_request_items_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;


--
-- Name: inventory_stock_request_items inventory_stock_request_items_request_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_request_items
    ADD CONSTRAINT inventory_stock_request_items_request_id_fkey FOREIGN KEY (request_id) REFERENCES public.inventory_stock_requests(id) ON DELETE CASCADE;


--
-- Name: inventory_stock_requests inventory_stock_requests_approved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_requests
    ADD CONSTRAINT inventory_stock_requests_approved_by_fkey FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_stock_requests inventory_stock_requests_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_requests
    ADD CONSTRAINT inventory_stock_requests_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: inventory_stock_requests inventory_stock_requests_handed_to_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_requests
    ADD CONSTRAINT inventory_stock_requests_handed_to_fkey FOREIGN KEY (handed_to) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_stock_requests inventory_stock_requests_picked_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_requests
    ADD CONSTRAINT inventory_stock_requests_picked_by_fkey FOREIGN KEY (picked_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_stock_requests inventory_stock_requests_requested_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_requests
    ADD CONSTRAINT inventory_stock_requests_requested_by_fkey FOREIGN KEY (requested_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_stock_requests inventory_stock_requests_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_requests
    ADD CONSTRAINT inventory_stock_requests_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE SET NULL;


--
-- Name: inventory_stock_requests inventory_stock_requests_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_stock_requests
    ADD CONSTRAINT inventory_stock_requests_warehouse_id_fkey FOREIGN KEY (warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE SET NULL;


--
-- Name: inventory_thresholds inventory_thresholds_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_thresholds
    ADD CONSTRAINT inventory_thresholds_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.equipment_categories(id) ON DELETE CASCADE;


--
-- Name: inventory_thresholds inventory_thresholds_warehouse_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_thresholds
    ADD CONSTRAINT inventory_thresholds_warehouse_id_fkey FOREIGN KEY (warehouse_id) REFERENCES public.inventory_warehouses(id) ON DELETE CASCADE;


--
-- Name: inventory_usage inventory_usage_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_usage
    ADD CONSTRAINT inventory_usage_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: inventory_usage inventory_usage_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_usage
    ADD CONSTRAINT inventory_usage_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE SET NULL;


--
-- Name: inventory_usage inventory_usage_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_usage
    ADD CONSTRAINT inventory_usage_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;


--
-- Name: inventory_usage inventory_usage_recorded_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_usage
    ADD CONSTRAINT inventory_usage_recorded_by_fkey FOREIGN KEY (recorded_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventory_usage inventory_usage_request_item_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_usage
    ADD CONSTRAINT inventory_usage_request_item_id_fkey FOREIGN KEY (request_item_id) REFERENCES public.inventory_stock_request_items(id) ON DELETE SET NULL;


--
-- Name: inventory_usage inventory_usage_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_usage
    ADD CONSTRAINT inventory_usage_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE SET NULL;


--
-- Name: inventory_warehouses inventory_warehouses_manager_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.inventory_warehouses
    ADD CONSTRAINT inventory_warehouses_manager_id_fkey FOREIGN KEY (manager_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: invoice_items invoice_items_invoice_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.invoice_items
    ADD CONSTRAINT invoice_items_invoice_id_fkey FOREIGN KEY (invoice_id) REFERENCES public.invoices(id) ON DELETE CASCADE;


--
-- Name: invoice_items invoice_items_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.invoice_items
    ADD CONSTRAINT invoice_items_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.products_services(id);


--
-- Name: invoice_items invoice_items_tax_rate_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.invoice_items
    ADD CONSTRAINT invoice_items_tax_rate_id_fkey FOREIGN KEY (tax_rate_id) REFERENCES public.tax_rates(id);


--
-- Name: invoices invoices_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: invoices invoices_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: invoices invoices_order_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_order_id_fkey FOREIGN KEY (order_id) REFERENCES public.orders(id) ON DELETE SET NULL;


--
-- Name: invoices invoices_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE SET NULL;


--
-- Name: leave_balances leave_balances_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.leave_balances
    ADD CONSTRAINT leave_balances_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: leave_balances leave_balances_leave_type_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.leave_balances
    ADD CONSTRAINT leave_balances_leave_type_id_fkey FOREIGN KEY (leave_type_id) REFERENCES public.leave_types(id) ON DELETE CASCADE;


--
-- Name: leave_calendar leave_calendar_branch_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.leave_calendar
    ADD CONSTRAINT leave_calendar_branch_id_fkey FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- Name: leave_requests leave_requests_approved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.leave_requests
    ADD CONSTRAINT leave_requests_approved_by_fkey FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: leave_requests leave_requests_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.leave_requests
    ADD CONSTRAINT leave_requests_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: leave_requests leave_requests_leave_type_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.leave_requests
    ADD CONSTRAINT leave_requests_leave_type_id_fkey FOREIGN KEY (leave_type_id) REFERENCES public.leave_types(id) ON DELETE CASCADE;


--
-- Name: mobile_notifications mobile_notifications_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mobile_notifications
    ADD CONSTRAINT mobile_notifications_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: mobile_tokens mobile_tokens_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mobile_tokens
    ADD CONSTRAINT mobile_tokens_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: mpesa_b2b_transactions mpesa_b2b_transactions_initiated_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_b2b_transactions
    ADD CONSTRAINT mpesa_b2b_transactions_initiated_by_fkey FOREIGN KEY (initiated_by) REFERENCES public.users(id);


--
-- Name: mpesa_b2c_transactions mpesa_b2c_transactions_initiated_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_b2c_transactions
    ADD CONSTRAINT mpesa_b2c_transactions_initiated_by_fkey FOREIGN KEY (initiated_by) REFERENCES public.users(id);


--
-- Name: mpesa_c2b_transactions mpesa_c2b_transactions_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_c2b_transactions
    ADD CONSTRAINT mpesa_c2b_transactions_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: mpesa_transactions mpesa_transactions_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.mpesa_transactions
    ADD CONSTRAINT mpesa_transactions_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: onu_discovery_log onu_discovery_log_olt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.onu_discovery_log
    ADD CONSTRAINT onu_discovery_log_olt_id_fkey FOREIGN KEY (olt_id) REFERENCES public.huawei_olts(id) ON DELETE CASCADE;


--
-- Name: onu_discovery_log onu_discovery_log_onu_type_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.onu_discovery_log
    ADD CONSTRAINT onu_discovery_log_onu_type_id_fkey FOREIGN KEY (onu_type_id) REFERENCES public.huawei_onu_types(id);


--
-- Name: onu_signal_history onu_signal_history_onu_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.onu_signal_history
    ADD CONSTRAINT onu_signal_history_onu_id_fkey FOREIGN KEY (onu_id) REFERENCES public.huawei_onus(id) ON DELETE CASCADE;


--
-- Name: onu_uptime_log onu_uptime_log_onu_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.onu_uptime_log
    ADD CONSTRAINT onu_uptime_log_onu_id_fkey FOREIGN KEY (onu_id) REFERENCES public.huawei_onus(id) ON DELETE CASCADE;


--
-- Name: orders orders_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: orders orders_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: orders orders_mpesa_transaction_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_mpesa_transaction_id_fkey FOREIGN KEY (mpesa_transaction_id) REFERENCES public.mpesa_transactions(id) ON DELETE SET NULL;


--
-- Name: orders orders_package_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_package_id_fkey FOREIGN KEY (package_id) REFERENCES public.service_packages(id) ON DELETE SET NULL;


--
-- Name: orders orders_salesperson_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_salesperson_id_fkey FOREIGN KEY (salesperson_id) REFERENCES public.salespersons(id) ON DELETE SET NULL;


--
-- Name: orders orders_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE SET NULL;


--
-- Name: payroll_commissions payroll_commissions_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.payroll_commissions
    ADD CONSTRAINT payroll_commissions_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: payroll_commissions payroll_commissions_payroll_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.payroll_commissions
    ADD CONSTRAINT payroll_commissions_payroll_id_fkey FOREIGN KEY (payroll_id) REFERENCES public.payroll(id) ON DELETE CASCADE;


--
-- Name: payroll payroll_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.payroll
    ADD CONSTRAINT payroll_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: performance_reviews performance_reviews_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.performance_reviews
    ADD CONSTRAINT performance_reviews_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: performance_reviews performance_reviews_reviewer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.performance_reviews
    ADD CONSTRAINT performance_reviews_reviewer_id_fkey FOREIGN KEY (reviewer_id) REFERENCES public.employees(id) ON DELETE SET NULL;


--
-- Name: products_services products_services_expense_account_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.products_services
    ADD CONSTRAINT products_services_expense_account_id_fkey FOREIGN KEY (expense_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: products_services products_services_income_account_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.products_services
    ADD CONSTRAINT products_services_income_account_id_fkey FOREIGN KEY (income_account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: products_services products_services_tax_rate_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.products_services
    ADD CONSTRAINT products_services_tax_rate_id_fkey FOREIGN KEY (tax_rate_id) REFERENCES public.tax_rates(id);


--
-- Name: purchase_order_items purchase_order_items_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id);


--
-- Name: purchase_order_items purchase_order_items_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.products_services(id);


--
-- Name: purchase_order_items purchase_order_items_purchase_order_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_purchase_order_id_fkey FOREIGN KEY (purchase_order_id) REFERENCES public.purchase_orders(id) ON DELETE CASCADE;


--
-- Name: purchase_order_items purchase_order_items_tax_rate_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_tax_rate_id_fkey FOREIGN KEY (tax_rate_id) REFERENCES public.tax_rates(id);


--
-- Name: purchase_orders purchase_orders_approved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_approved_by_fkey FOREIGN KEY (approved_by) REFERENCES public.users(id);


--
-- Name: purchase_orders purchase_orders_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: purchase_orders purchase_orders_vendor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_vendor_id_fkey FOREIGN KEY (vendor_id) REFERENCES public.vendors(id) ON DELETE SET NULL;


--
-- Name: quote_items quote_items_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.quote_items
    ADD CONSTRAINT quote_items_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.products_services(id);


--
-- Name: quote_items quote_items_quote_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.quote_items
    ADD CONSTRAINT quote_items_quote_id_fkey FOREIGN KEY (quote_id) REFERENCES public.quotes(id) ON DELETE CASCADE;


--
-- Name: quote_items quote_items_tax_rate_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.quote_items
    ADD CONSTRAINT quote_items_tax_rate_id_fkey FOREIGN KEY (tax_rate_id) REFERENCES public.tax_rates(id);


--
-- Name: quotes quotes_converted_to_invoice_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.quotes
    ADD CONSTRAINT quotes_converted_to_invoice_id_fkey FOREIGN KEY (converted_to_invoice_id) REFERENCES public.invoices(id);


--
-- Name: quotes quotes_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.quotes
    ADD CONSTRAINT quotes_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: quotes quotes_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.quotes
    ADD CONSTRAINT quotes_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: role_permissions role_permissions_permission_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.role_permissions
    ADD CONSTRAINT role_permissions_permission_id_fkey FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_permissions role_permissions_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.role_permissions
    ADD CONSTRAINT role_permissions_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: salary_advance_repayments salary_advance_repayments_advance_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.salary_advance_repayments
    ADD CONSTRAINT salary_advance_repayments_advance_id_fkey FOREIGN KEY (advance_id) REFERENCES public.salary_advances(id) ON DELETE CASCADE;


--
-- Name: salary_advance_repayments salary_advance_repayments_payroll_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.salary_advance_repayments
    ADD CONSTRAINT salary_advance_repayments_payroll_id_fkey FOREIGN KEY (payroll_id) REFERENCES public.payroll(id) ON DELETE SET NULL;


--
-- Name: salary_advances salary_advances_approved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.salary_advances
    ADD CONSTRAINT salary_advances_approved_by_fkey FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: salary_advances salary_advances_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.salary_advances
    ADD CONSTRAINT salary_advances_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: salary_advances salary_advances_mpesa_b2c_transaction_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.salary_advances
    ADD CONSTRAINT salary_advances_mpesa_b2c_transaction_id_fkey FOREIGN KEY (mpesa_b2c_transaction_id) REFERENCES public.mpesa_b2c_transactions(id);


--
-- Name: sales_commissions sales_commissions_order_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.sales_commissions
    ADD CONSTRAINT sales_commissions_order_id_fkey FOREIGN KEY (order_id) REFERENCES public.orders(id) ON DELETE CASCADE;


--
-- Name: sales_commissions sales_commissions_salesperson_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.sales_commissions
    ADD CONSTRAINT sales_commissions_salesperson_id_fkey FOREIGN KEY (salesperson_id) REFERENCES public.salespersons(id) ON DELETE CASCADE;


--
-- Name: salespersons salespersons_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.salespersons
    ADD CONSTRAINT salespersons_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE SET NULL;


--
-- Name: salespersons salespersons_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.salespersons
    ADD CONSTRAINT salespersons_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: sla_policies sla_policies_escalation_to_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.sla_policies
    ADD CONSTRAINT sla_policies_escalation_to_fkey FOREIGN KEY (escalation_to) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: sms_logs sms_logs_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.sms_logs
    ADD CONSTRAINT sms_logs_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;


--
-- Name: team_members team_members_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.team_members
    ADD CONSTRAINT team_members_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: team_members team_members_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.team_members
    ADD CONSTRAINT team_members_team_id_fkey FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE CASCADE;


--
-- Name: teams teams_branch_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT teams_branch_id_fkey FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- Name: teams teams_leader_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT teams_leader_id_fkey FOREIGN KEY (leader_id) REFERENCES public.employees(id);


--
-- Name: technician_kit_items technician_kit_items_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.technician_kit_items
    ADD CONSTRAINT technician_kit_items_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.equipment_categories(id) ON DELETE SET NULL;


--
-- Name: technician_kit_items technician_kit_items_equipment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.technician_kit_items
    ADD CONSTRAINT technician_kit_items_equipment_id_fkey FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE SET NULL;


--
-- Name: technician_kit_items technician_kit_items_kit_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.technician_kit_items
    ADD CONSTRAINT technician_kit_items_kit_id_fkey FOREIGN KEY (kit_id) REFERENCES public.technician_kits(id) ON DELETE CASCADE;


--
-- Name: technician_kits technician_kits_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.technician_kits
    ADD CONSTRAINT technician_kits_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE SET NULL;


--
-- Name: technician_kits technician_kits_issued_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.technician_kits
    ADD CONSTRAINT technician_kits_issued_by_fkey FOREIGN KEY (issued_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ticket_comments ticket_comments_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_comments
    ADD CONSTRAINT ticket_comments_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;


--
-- Name: ticket_comments ticket_comments_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_comments
    ADD CONSTRAINT ticket_comments_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ticket_earnings ticket_earnings_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_earnings
    ADD CONSTRAINT ticket_earnings_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE CASCADE;


--
-- Name: ticket_earnings ticket_earnings_payroll_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_earnings
    ADD CONSTRAINT ticket_earnings_payroll_id_fkey FOREIGN KEY (payroll_id) REFERENCES public.payroll(id) ON DELETE SET NULL;


--
-- Name: ticket_earnings ticket_earnings_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_earnings
    ADD CONSTRAINT ticket_earnings_team_id_fkey FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE SET NULL;


--
-- Name: ticket_earnings ticket_earnings_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_earnings
    ADD CONSTRAINT ticket_earnings_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;


--
-- Name: ticket_escalations ticket_escalations_escalated_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_escalations
    ADD CONSTRAINT ticket_escalations_escalated_by_fkey FOREIGN KEY (escalated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ticket_escalations ticket_escalations_escalated_to_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_escalations
    ADD CONSTRAINT ticket_escalations_escalated_to_fkey FOREIGN KEY (escalated_to) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ticket_escalations ticket_escalations_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_escalations
    ADD CONSTRAINT ticket_escalations_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;


--
-- Name: ticket_satisfaction_ratings ticket_satisfaction_ratings_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_satisfaction_ratings
    ADD CONSTRAINT ticket_satisfaction_ratings_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE CASCADE;


--
-- Name: ticket_satisfaction_ratings ticket_satisfaction_ratings_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_satisfaction_ratings
    ADD CONSTRAINT ticket_satisfaction_ratings_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;


--
-- Name: ticket_service_fees ticket_service_fees_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_service_fees
    ADD CONSTRAINT ticket_service_fees_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: ticket_service_fees ticket_service_fees_fee_type_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_service_fees
    ADD CONSTRAINT ticket_service_fees_fee_type_id_fkey FOREIGN KEY (fee_type_id) REFERENCES public.service_fee_types(id) ON DELETE SET NULL;


--
-- Name: ticket_service_fees ticket_service_fees_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_service_fees
    ADD CONSTRAINT ticket_service_fees_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;


--
-- Name: ticket_sla_logs ticket_sla_logs_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_sla_logs
    ADD CONSTRAINT ticket_sla_logs_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;


--
-- Name: ticket_status_tokens ticket_status_tokens_employee_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_status_tokens
    ADD CONSTRAINT ticket_status_tokens_employee_id_fkey FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE SET NULL;


--
-- Name: ticket_status_tokens ticket_status_tokens_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_status_tokens
    ADD CONSTRAINT ticket_status_tokens_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;


--
-- Name: ticket_templates ticket_templates_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.ticket_templates
    ADD CONSTRAINT ticket_templates_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: tickets tickets_assigned_to_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_assigned_to_fkey FOREIGN KEY (assigned_to) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: tickets tickets_branch_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_branch_id_fkey FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- Name: tickets tickets_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: tickets tickets_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE CASCADE;


--
-- Name: tickets tickets_sla_policy_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_sla_policy_id_fkey FOREIGN KEY (sla_policy_id) REFERENCES public.sla_policies(id) ON DELETE SET NULL;


--
-- Name: tickets tickets_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_team_id_fkey FOREIGN KEY (team_id) REFERENCES public.teams(id);


--
-- Name: user_notifications user_notifications_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.user_notifications
    ADD CONSTRAINT user_notifications_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: users users_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE SET NULL;


--
-- Name: vendor_bill_items vendor_bill_items_account_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendor_bill_items
    ADD CONSTRAINT vendor_bill_items_account_id_fkey FOREIGN KEY (account_id) REFERENCES public.chart_of_accounts(id);


--
-- Name: vendor_bill_items vendor_bill_items_bill_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendor_bill_items
    ADD CONSTRAINT vendor_bill_items_bill_id_fkey FOREIGN KEY (bill_id) REFERENCES public.vendor_bills(id) ON DELETE CASCADE;


--
-- Name: vendor_bill_items vendor_bill_items_tax_rate_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendor_bill_items
    ADD CONSTRAINT vendor_bill_items_tax_rate_id_fkey FOREIGN KEY (tax_rate_id) REFERENCES public.tax_rates(id);


--
-- Name: vendor_bills vendor_bills_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendor_bills
    ADD CONSTRAINT vendor_bills_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: vendor_bills vendor_bills_purchase_order_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendor_bills
    ADD CONSTRAINT vendor_bills_purchase_order_id_fkey FOREIGN KEY (purchase_order_id) REFERENCES public.purchase_orders(id);


--
-- Name: vendor_bills vendor_bills_vendor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendor_bills
    ADD CONSTRAINT vendor_bills_vendor_id_fkey FOREIGN KEY (vendor_id) REFERENCES public.vendors(id) ON DELETE SET NULL;


--
-- Name: vendor_payments vendor_payments_bill_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendor_payments
    ADD CONSTRAINT vendor_payments_bill_id_fkey FOREIGN KEY (bill_id) REFERENCES public.vendor_bills(id) ON DELETE SET NULL;


--
-- Name: vendor_payments vendor_payments_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendor_payments
    ADD CONSTRAINT vendor_payments_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id);


--
-- Name: vendor_payments vendor_payments_vendor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendor_payments
    ADD CONSTRAINT vendor_payments_vendor_id_fkey FOREIGN KEY (vendor_id) REFERENCES public.vendors(id) ON DELETE SET NULL;


--
-- Name: vlan_history vlan_history_vlan_record_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vlan_history
    ADD CONSTRAINT vlan_history_vlan_record_id_fkey FOREIGN KEY (vlan_record_id) REFERENCES public.device_vlans(id) ON DELETE CASCADE;


--
-- Name: whatsapp_conversations whatsapp_conversations_assigned_to_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.whatsapp_conversations
    ADD CONSTRAINT whatsapp_conversations_assigned_to_fkey FOREIGN KEY (assigned_to) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: whatsapp_conversations whatsapp_conversations_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.whatsapp_conversations
    ADD CONSTRAINT whatsapp_conversations_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: whatsapp_logs whatsapp_logs_complaint_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.whatsapp_logs
    ADD CONSTRAINT whatsapp_logs_complaint_id_fkey FOREIGN KEY (complaint_id) REFERENCES public.complaints(id) ON DELETE CASCADE;


--
-- Name: whatsapp_logs whatsapp_logs_order_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.whatsapp_logs
    ADD CONSTRAINT whatsapp_logs_order_id_fkey FOREIGN KEY (order_id) REFERENCES public.orders(id) ON DELETE CASCADE;


--
-- Name: whatsapp_logs whatsapp_logs_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.whatsapp_logs
    ADD CONSTRAINT whatsapp_logs_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;


--
-- Name: whatsapp_messages whatsapp_messages_conversation_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.whatsapp_messages
    ADD CONSTRAINT whatsapp_messages_conversation_id_fkey FOREIGN KEY (conversation_id) REFERENCES public.whatsapp_conversations(id) ON DELETE CASCADE;


--
-- Name: whatsapp_messages whatsapp_messages_sent_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.whatsapp_messages
    ADD CONSTRAINT whatsapp_messages_sent_by_fkey FOREIGN KEY (sent_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: wireguard_peers wireguard_peers_server_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.wireguard_peers
    ADD CONSTRAINT wireguard_peers_server_id_fkey FOREIGN KEY (server_id) REFERENCES public.wireguard_servers(id) ON DELETE CASCADE;


--
-- Name: wireguard_subnets wireguard_subnets_vpn_peer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.wireguard_subnets
    ADD CONSTRAINT wireguard_subnets_vpn_peer_id_fkey FOREIGN KEY (vpn_peer_id) REFERENCES public.wireguard_peers(id) ON DELETE CASCADE;


--
-- Name: wireguard_sync_logs wireguard_sync_logs_server_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.wireguard_sync_logs
    ADD CONSTRAINT wireguard_sync_logs_server_id_fkey FOREIGN KEY (server_id) REFERENCES public.wireguard_servers(id);


--
-- Name: DEFAULT PRIVILEGES FOR SEQUENCES; Type: DEFAULT ACL; Schema: public; Owner: cloud_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE cloud_admin IN SCHEMA public GRANT ALL ON SEQUENCES TO neon_superuser WITH GRANT OPTION;


--
-- Name: DEFAULT PRIVILEGES FOR TABLES; Type: DEFAULT ACL; Schema: public; Owner: cloud_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE cloud_admin IN SCHEMA public GRANT ALL ON TABLES TO neon_superuser WITH GRANT OPTION;


--
-- PostgreSQL database dump complete
--

\unrestrict iAsDMFYLD7uyJ3mm5pO9Nh7vpObuN2D7XTZ1AAfi6ElfHP9wp7KCmemv1urdrP6

