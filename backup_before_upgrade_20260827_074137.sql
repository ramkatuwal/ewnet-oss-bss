--
-- PostgreSQL database dump
--

-- Dumped from database version 17.5 (Debian 17.5-1.pgdg110+1)
-- Dumped by pg_dump version 17.5 (Debian 17.5-1.pgdg110+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: public; Type: SCHEMA; Schema: -; Owner: ewnet
--

-- *not* creating schema, since initdb creates it


ALTER SCHEMA public OWNER TO ewnet;

--
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: ewnet
--

COMMENT ON SCHEMA public IS '';


--
-- Name: tiger; Type: SCHEMA; Schema: -; Owner: ewnet
--

CREATE SCHEMA tiger;


ALTER SCHEMA tiger OWNER TO ewnet;

--
-- Name: tiger_data; Type: SCHEMA; Schema: -; Owner: ewnet
--

CREATE SCHEMA tiger_data;


ALTER SCHEMA tiger_data OWNER TO ewnet;

--
-- Name: topology; Type: SCHEMA; Schema: -; Owner: ewnet
--

CREATE SCHEMA topology;


ALTER SCHEMA topology OWNER TO ewnet;

--
-- Name: SCHEMA topology; Type: COMMENT; Schema: -; Owner: ewnet
--

COMMENT ON SCHEMA topology IS 'PostGIS Topology schema';


--
-- Name: fuzzystrmatch; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS fuzzystrmatch WITH SCHEMA public;


--
-- Name: EXTENSION fuzzystrmatch; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION fuzzystrmatch IS 'determine similarities and distance between strings';


--
-- Name: postgis; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS postgis WITH SCHEMA public;


--
-- Name: EXTENSION postgis; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION postgis IS 'PostGIS geometry and geography spatial types and functions';


--
-- Name: postgis_tiger_geocoder; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS postgis_tiger_geocoder WITH SCHEMA tiger;


--
-- Name: EXTENSION postgis_tiger_geocoder; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION postgis_tiger_geocoder IS 'PostGIS tiger geocoder and reverse geocoder';


--
-- Name: postgis_topology; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS postgis_topology WITH SCHEMA topology;


--
-- Name: EXTENSION postgis_topology; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION postgis_topology IS 'PostGIS topology spatial types and functions';


--
-- Name: municipality_type_enum; Type: TYPE; Schema: public; Owner: ewnet
--

CREATE TYPE public.municipality_type_enum AS ENUM (
    'metropolitan',
    'sub-metropolitan',
    'municipality',
    'rural-municipality'
);


ALTER TYPE public.municipality_type_enum OWNER TO ewnet;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: branches; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.branches (
    id bigint NOT NULL,
    region_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255) NOT NULL,
    address text,
    city character varying(255),
    state character varying(255),
    postal_code character varying(255),
    country character varying(255) DEFAULT 'Nepal'::character varying NOT NULL,
    phone character varying(255),
    email character varying(255),
    latitude character varying(255),
    longitude character varying(255),
    settings json,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.branches OWNER TO ewnet;

--
-- Name: branches_id_seq; Type: SEQUENCE; Schema: public; Owner: ewnet
--

CREATE SEQUENCE public.branches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.branches_id_seq OWNER TO ewnet;

--
-- Name: branches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: ewnet
--

ALTER SEQUENCE public.branches_id_seq OWNED BY public.branches.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


ALTER TABLE public.cache OWNER TO ewnet;

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO ewnet;

--
-- Name: companies; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.companies (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    registration_number character varying(255),
    pan_number character varying(255),
    email character varying(255),
    phone character varying(255),
    address text,
    city character varying(255),
    state character varying(255),
    postal_code character varying(255),
    country character varying(255) DEFAULT 'Nepal'::character varying NOT NULL,
    website character varying(255),
    settings json,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.companies OWNER TO ewnet;

--
-- Name: companies_id_seq; Type: SEQUENCE; Schema: public; Owner: ewnet
--

CREATE SEQUENCE public.companies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.companies_id_seq OWNER TO ewnet;

--
-- Name: companies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: ewnet
--

ALTER SEQUENCE public.companies_id_seq OWNED BY public.companies.id;


--
-- Name: departments; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.departments (
    id bigint NOT NULL,
    company_id bigint,
    branch_id bigint,
    name character varying(255) NOT NULL,
    code character varying(255) NOT NULL,
    description text,
    settings json,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.departments OWNER TO ewnet;

--
-- Name: departments_id_seq; Type: SEQUENCE; Schema: public; Owner: ewnet
--

CREATE SEQUENCE public.departments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.departments_id_seq OWNER TO ewnet;

--
-- Name: departments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: ewnet
--

ALTER SEQUENCE public.departments_id_seq OWNED BY public.departments.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.failed_jobs OWNER TO ewnet;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: ewnet
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.failed_jobs_id_seq OWNER TO ewnet;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: ewnet
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE public.job_batches OWNER TO ewnet;

--
-- Name: jobs; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE public.jobs OWNER TO ewnet;

--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: ewnet
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.jobs_id_seq OWNER TO ewnet;

--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: ewnet
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO ewnet;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: ewnet
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO ewnet;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: ewnet
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.model_has_permissions (
    permission_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


ALTER TABLE public.model_has_permissions OWNER TO ewnet;

--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.model_has_roles (
    role_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


ALTER TABLE public.model_has_roles OWNER TO ewnet;

--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


ALTER TABLE public.password_reset_tokens OWNER TO ewnet;

--
-- Name: permission_tables; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.permission_tables (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.permission_tables OWNER TO ewnet;

--
-- Name: permission_tables_id_seq; Type: SEQUENCE; Schema: public; Owner: ewnet
--

CREATE SEQUENCE public.permission_tables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.permission_tables_id_seq OWNER TO ewnet;

--
-- Name: permission_tables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: ewnet
--

ALTER SEQUENCE public.permission_tables_id_seq OWNED BY public.permission_tables.id;


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.permissions OWNER TO ewnet;

--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: ewnet
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.permissions_id_seq OWNER TO ewnet;

--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: ewnet
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.personal_access_tokens OWNER TO ewnet;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: ewnet
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.personal_access_tokens_id_seq OWNER TO ewnet;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: ewnet
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: regions; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.regions (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255) NOT NULL,
    description text,
    city character varying(255),
    state character varying(255),
    country character varying(255) DEFAULT 'Nepal'::character varying NOT NULL,
    settings json,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.regions OWNER TO ewnet;

--
-- Name: regions_id_seq; Type: SEQUENCE; Schema: public; Owner: ewnet
--

CREATE SEQUENCE public.regions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.regions_id_seq OWNER TO ewnet;

--
-- Name: regions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: ewnet
--

ALTER SEQUENCE public.regions_id_seq OWNED BY public.regions.id;


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.role_has_permissions (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);


ALTER TABLE public.role_has_permissions OWNER TO ewnet;

--
-- Name: roles; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.roles OWNER TO ewnet;

--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: ewnet
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.roles_id_seq OWNER TO ewnet;

--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: ewnet
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


ALTER TABLE public.sessions OWNER TO ewnet;

--
-- Name: users; Type: TABLE; Schema: public; Owner: ewnet
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.users OWNER TO ewnet;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: ewnet
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO ewnet;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: ewnet
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: branches id; Type: DEFAULT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.branches ALTER COLUMN id SET DEFAULT nextval('public.branches_id_seq'::regclass);


--
-- Name: companies id; Type: DEFAULT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.companies ALTER COLUMN id SET DEFAULT nextval('public.companies_id_seq'::regclass);


--
-- Name: departments id; Type: DEFAULT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.departments ALTER COLUMN id SET DEFAULT nextval('public.departments_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: permission_tables id; Type: DEFAULT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.permission_tables ALTER COLUMN id SET DEFAULT nextval('public.permission_tables_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: regions id; Type: DEFAULT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.regions ALTER COLUMN id SET DEFAULT nextval('public.regions_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: branches; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.branches (id, region_id, name, code, address, city, state, postal_code, country, phone, email, latitude, longitude, settings, is_active, created_at, updated_at, deleted_at) FROM stdin;
1082	1503	BR1	12	\N	\N	\N	\N	Nepal	\N	\N	\N	\N	\N	t	2026-08-26 09:33:56	2026-08-26 15:01:36	\N
1081	1500	test	12121	Bhasi	Mahendranagar	Karnali	\N	Nepal	083521003	ram.katuwal977@gmail.com	\N	\N	\N	t	2026-08-26 08:10:52	2026-08-26 08:11:12	2026-08-26 08:11:12
1080	1503	Kathmandu Headquarters	KTM-HQ	Baluwatar, Kathmandu	Kathmandu	Bagmati	\N	Nepal	+977-1-1234567	hq@ewnet.com.np	\N	\N	\N	t	2026-08-26 06:12:29	2026-08-26 12:34:55	2026-08-26 12:34:55
\.


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: companies; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.companies (id, name, registration_number, pan_number, email, phone, address, city, state, postal_code, country, website, settings, is_active, created_at, updated_at, deleted_at) FROM stdin;
2490	Test	12121212	\N	noc@ewnet.com.np	\N	\N	\N	\N	\N	Nepal	\N	\N	t	2026-08-26 07:25:01	2026-08-26 09:33:17	2026-08-26 09:33:17
2489	Everest Wireless Network Pvt. Ltd.	123456111	123456	info@ewnet.com.np	+977-83597000	Kathmandu, Nepal	Birendranagar	Bagmati	\N	Nepal	https://ewnet.com.np	\N	t	2026-08-26 06:12:29	2026-08-26 12:34:23	\N
\.


--
-- Data for Name: departments; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.departments (id, company_id, branch_id, name, code, description, settings, is_active, created_at, updated_at, deleted_at) FROM stdin;
430	2489	1082	IT	121112	\N	\N	t	2026-08-26 10:50:40	2026-08-26 10:50:44	2026-08-26 10:50:44
421	2489	1082	Network Operations Center	NOC	Core NOC team	\N	t	2026-08-26 06:12:29	2026-08-26 12:35:05	\N
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_08_23_170801_create_companies_table	1
5	2026_08_23_170802_create_regions_table	1
6	2026_08_23_170803_create_branches_table	1
7	2026_08_23_170804_create_departments_table	1
8	2026_08_23_170805_create_designations_table	1
9	2026_08_23_170806_create_employees_table	1
10	2026_08_23_170900_add_foreign_key_manager_id_to_departments_table	1
11	2026_08_23_171114_add_foreign_key_manager_id_to_departments_table	1
12	2026_08_23_173000_create_personal_access_tokens_table	1
13	2026_08_23_173426_create_permission_tables	1
14	2026_08_23_174000_create_permission_tables	1
15	2026_08_24_000000_create_countries_table	1
16	2026_08_24_000001_create_provinces_table	1
17	2026_08_24_000002_create_districts_table	1
18	2026_08_24_000003_create_municipalities_table	1
19	2026_08_24_000004_create_wards_table	1
20	2026_08_24_000005_create_localities_table	1
24	2026_08_24_000009_add_enum_types	1
35	2026_08_26_100003_drop_manager_id_from_departments	3
36	2026_08_26_100004_drop_employees_and_designations_tables	3
\.


--
-- Data for Name: model_has_permissions; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.model_has_permissions (permission_id, model_type, model_id) FROM stdin;
\.


--
-- Data for Name: model_has_roles; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.model_has_roles (role_id, model_type, model_id) FROM stdin;
19	App\\Models\\User	742
19	App\\Models\\User	2503
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: permission_tables; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.permission_tables (id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.permissions (id, name, guard_name, created_at, updated_at) FROM stdin;
587	companies.view	web	2026-08-25 11:28:30	2026-08-25 11:28:30
588	companies.create	web	2026-08-25 11:28:30	2026-08-25 11:28:30
589	companies.update	web	2026-08-25 11:28:30	2026-08-25 11:28:30
590	companies.delete	web	2026-08-25 11:28:30	2026-08-25 11:28:30
591	regions.view	web	2026-08-25 11:28:30	2026-08-25 11:28:30
592	regions.create	web	2026-08-25 11:28:30	2026-08-25 11:28:30
593	regions.update	web	2026-08-25 11:28:31	2026-08-25 11:28:31
594	regions.delete	web	2026-08-25 11:28:31	2026-08-25 11:28:31
595	branches.view	web	2026-08-25 11:28:31	2026-08-25 11:28:31
596	branches.create	web	2026-08-25 11:28:31	2026-08-25 11:28:31
597	branches.update	web	2026-08-25 11:28:31	2026-08-25 11:28:31
598	branches.delete	web	2026-08-25 11:28:31	2026-08-25 11:28:31
599	departments.view	web	2026-08-25 11:28:31	2026-08-25 11:28:31
600	departments.create	web	2026-08-25 11:28:31	2026-08-25 11:28:31
601	departments.update	web	2026-08-25 11:28:31	2026-08-25 11:28:31
602	departments.delete	web	2026-08-25 11:28:31	2026-08-25 11:28:31
611	users.view	web	2026-08-25 11:28:31	2026-08-25 11:28:31
612	users.create	web	2026-08-25 11:28:31	2026-08-25 11:28:31
613	users.update	web	2026-08-25 11:28:31	2026-08-25 11:28:31
614	users.delete	web	2026-08-25 11:28:31	2026-08-25 11:28:31
615	roles.view	web	2026-08-25 11:28:31	2026-08-25 11:28:31
616	roles.create	web	2026-08-25 11:28:31	2026-08-25 11:28:31
617	roles.update	web	2026-08-25 11:28:31	2026-08-25 11:28:31
618	roles.delete	web	2026-08-25 11:28:31	2026-08-25 11:28:31
619	permissions.view	web	2026-08-25 11:28:31	2026-08-25 11:28:31
620	permissions.create	web	2026-08-25 11:28:31	2026-08-25 11:28:31
621	permissions.update	web	2026-08-25 11:28:31	2026-08-25 11:28:31
622	permissions.delete	web	2026-08-25 11:28:31	2026-08-25 11:28:31
391	system.debug.view	web	2026-08-25 11:23:13	2026-08-25 11:23:13
\.


--
-- Data for Name: personal_access_tokens; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) FROM stdin;
77	App\\Models\\User	742	api-test	08e58c6a30ff335c33b4e11ef09326397bf6d2acf01b415eff2da4e2b1e3d2c7	["*"]	2026-08-27 05:16:47	\N	2026-08-27 05:16:46	2026-08-27 05:16:47
\.


--
-- Data for Name: regions; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.regions (id, company_id, name, code, description, city, state, country, settings, is_active, created_at, updated_at, deleted_at) FROM stdin;
1502	2490	test	1212	\N	Mahendranagar	\N	Nepal	\N	t	2026-08-26 07:48:05	2026-08-26 07:48:10	2026-08-26 07:48:10
1574	2489	Test Region	TEST-REG	Test region created during system validation	\N	\N	Nepal	\N	t	2026-08-27 05:11:26	2026-08-27 05:11:26	\N
1503	2489	HO	REG-1	\N	\N	\N	Nepal	\N	t	2026-08-26 09:33:35	2026-08-26 09:33:35	\N
1500	2489	Kathmandu Valley Region	KTM-VALLEYq	Central region covering Kathmandu Valley	Kathmandu	Bagmati	Nepal	\N	t	2026-08-26 06:12:29	2026-08-26 12:34:33	2026-08-26 12:34:33
1501	2489	Pokhara Region	POKHARA	Western region covering Pokhara	Pokhara	Gandaki	Nepal	\N	t	2026-08-26 06:12:29	2026-08-26 06:12:29	\N
\.


--
-- Data for Name: role_has_permissions; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.role_has_permissions (permission_id, role_id) FROM stdin;
587	19
588	19
589	19
590	19
591	19
592	19
593	19
594	19
595	19
596	19
597	19
598	19
599	19
600	19
601	19
602	19
611	19
612	19
613	19
614	19
615	19
616	19
617	19
618	19
619	19
620	19
621	19
622	19
391	19
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.roles (id, name, guard_name, created_at, updated_at) FROM stdin;
19	Super Admin	web	2026-08-25 11:28:31	2026-08-25 11:28:31
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
\.


--
-- Data for Name: spatial_ref_sys; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.spatial_ref_sys (srid, auth_name, auth_srid, srtext, proj4text) FROM stdin;
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: ewnet
--

COPY public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at) FROM stdin;
742	System Administrator	admin@ewnet.com.np	\N	$2y$12$f4eXicXU5HchZiADjjLfyO5I2Rr/vzZD5Q1kqaEx0bzXG496aoaSG	\N	2026-08-25 11:28:35	2026-08-26 06:12:31
2503	ram	ram.katuwal@ewnet.com.np	\N	$2y$12$lyK.p3mehy10QQGgrz919e1ZGpgwYYotfuaDy8SE/DQSfANRT86QC	\N	2026-08-26 09:39:22	2026-08-26 09:39:22
2504	Fiber-team	fiber@ewnet.com.np	\N	$2y$12$JXXNg5TSoHRrIHP1ZUe27ewLRJbtMgbWxn.9zmiGuBkQkJdum0NhO	\N	2026-08-26 10:49:15	2026-08-26 10:49:15
\.


--
-- Data for Name: geocode_settings; Type: TABLE DATA; Schema: tiger; Owner: ewnet
--

COPY tiger.geocode_settings (name, setting, unit, category, short_desc) FROM stdin;
\.


--
-- Data for Name: pagc_gaz; Type: TABLE DATA; Schema: tiger; Owner: ewnet
--

COPY tiger.pagc_gaz (id, seq, word, stdword, token, is_custom) FROM stdin;
\.


--
-- Data for Name: pagc_lex; Type: TABLE DATA; Schema: tiger; Owner: ewnet
--

COPY tiger.pagc_lex (id, seq, word, stdword, token, is_custom) FROM stdin;
\.


--
-- Data for Name: pagc_rules; Type: TABLE DATA; Schema: tiger; Owner: ewnet
--

COPY tiger.pagc_rules (id, rule, is_custom) FROM stdin;
\.


--
-- Data for Name: topology; Type: TABLE DATA; Schema: topology; Owner: ewnet
--

COPY topology.topology (id, name, srid, "precision", hasz) FROM stdin;
\.


--
-- Data for Name: layer; Type: TABLE DATA; Schema: topology; Owner: ewnet
--

COPY topology.layer (topology_id, layer_id, schema_name, table_name, feature_column, feature_type, level, child_id) FROM stdin;
\.


--
-- Name: branches_id_seq; Type: SEQUENCE SET; Schema: public; Owner: ewnet
--

SELECT pg_catalog.setval('public.branches_id_seq', 1128, true);


--
-- Name: companies_id_seq; Type: SEQUENCE SET; Schema: public; Owner: ewnet
--

SELECT pg_catalog.setval('public.companies_id_seq', 2594, true);


--
-- Name: departments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: ewnet
--

SELECT pg_catalog.setval('public.departments_id_seq', 445, true);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: ewnet
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: ewnet
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: ewnet
--

SELECT pg_catalog.setval('public.migrations_id_seq', 36, true);


--
-- Name: permission_tables_id_seq; Type: SEQUENCE SET; Schema: public; Owner: ewnet
--

SELECT pg_catalog.setval('public.permission_tables_id_seq', 1, false);


--
-- Name: permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: ewnet
--

SELECT pg_catalog.setval('public.permissions_id_seq', 2035, true);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: ewnet
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 77, true);


--
-- Name: regions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: ewnet
--

SELECT pg_catalog.setval('public.regions_id_seq', 1574, true);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: ewnet
--

SELECT pg_catalog.setval('public.roles_id_seq', 71, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: ewnet
--

SELECT pg_catalog.setval('public.users_id_seq', 2660, true);


--
-- Name: topology_id_seq; Type: SEQUENCE SET; Schema: topology; Owner: ewnet
--

SELECT pg_catalog.setval('topology.topology_id_seq', 1, false);


--
-- Name: branches branches_code_unique; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_code_unique UNIQUE (code);


--
-- Name: branches branches_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: companies companies_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_pkey PRIMARY KEY (id);


--
-- Name: companies companies_registration_number_unique; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_registration_number_unique UNIQUE (registration_number);


--
-- Name: departments departments_code_unique; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_code_unique UNIQUE (code);


--
-- Name: departments departments_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (permission_id, model_id, model_type);


--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (role_id, model_id, model_type);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: permission_tables permission_tables_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.permission_tables
    ADD CONSTRAINT permission_tables_pkey PRIMARY KEY (id);


--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: regions regions_code_unique; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.regions
    ADD CONSTRAINT regions_code_unique UNIQUE (code);


--
-- Name: regions regions_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.regions
    ADD CONSTRAINT regions_pkey PRIMARY KEY (id);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: branches_region_id_is_active_index; Type: INDEX; Schema: public; Owner: ewnet
--

CREATE INDEX branches_region_id_is_active_index ON public.branches USING btree (region_id, is_active);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: ewnet
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: ewnet
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: departments_company_id_branch_id_is_active_index; Type: INDEX; Schema: public; Owner: ewnet
--

CREATE INDEX departments_company_id_branch_id_is_active_index ON public.departments USING btree (company_id, branch_id, is_active);


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: public; Owner: ewnet
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: ewnet
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: ewnet
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: ewnet
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: ewnet
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: regions_company_id_is_active_index; Type: INDEX; Schema: public; Owner: ewnet
--

CREATE INDEX regions_company_id_is_active_index ON public.regions USING btree (company_id, is_active);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: ewnet
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: ewnet
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: branches branches_region_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_region_id_foreign FOREIGN KEY (region_id) REFERENCES public.regions(id) ON DELETE CASCADE;


--
-- Name: departments departments_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- Name: departments departments_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: regions regions_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.regions
    ADD CONSTRAINT regions_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: ewnet
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: ewnet
--

REVOKE USAGE ON SCHEMA public FROM PUBLIC;


--
-- PostgreSQL database dump complete
--

