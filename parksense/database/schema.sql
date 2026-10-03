-- ParkSense (Supabase / PostgreSQL): run once in the Supabase dashboard -> SQL Editor -> New query.

CREATE TABLE IF NOT EXISTS public.users (
  id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  full_name     VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,                  -- bcrypt hash, never the password
  role          TEXT         NOT NULL DEFAULT 'guard' CHECK (role IN ('admin', 'guard')),
  is_active     BOOLEAN      NOT NULL DEFAULT TRUE,     -- set to false to disable an account instantly
  last_login_at TIMESTAMPTZ  NULL,
  created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS public.login_attempts (
  id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  username     VARCHAR(50) NOT NULL,
  ip_address   VARCHAR(45) NOT NULL,
  success      BOOLEAN     NOT NULL,
  attempted_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_login_attempts_user ON public.login_attempts (username, attempted_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip   ON public.login_attempts (ip_address, attempted_at);

CREATE TABLE IF NOT EXISTS public.slots (
  id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  label      VARCHAR(30) NOT NULL UNIQUE,
  zone       VARCHAR(80) NOT NULL,
  status     TEXT NOT NULL DEFAULT 'vacant' CHECK (status IN ('vacant', 'occupied', 'out_of_service')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS public.occupancy_logs (
  id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  slot_id     BIGINT NULL REFERENCES public.slots(id) ON DELETE SET NULL,
  slot_label  VARCHAR(30) NOT NULL,
  status      TEXT NOT NULL CHECK (status IN ('vacant', 'occupied', 'out_of_service')),
  source      TEXT NOT NULL DEFAULT 'manual' CHECK (source IN ('manual', 'sensor')),
  changed_by  BIGINT NULL REFERENCES public.users(id) ON DELETE SET NULL,
  occurred_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_occupancy_logs_slot_time
  ON public.occupancy_logs (slot_id, occurred_at DESC);

-- Supabase publishes every table in "public" through its REST API, reachable with the anon API key.
-- Row Level Security with no policies closes that door; the PHP site connects as the database
-- owner, which is not affected by RLS.
ALTER TABLE public.users          ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.login_attempts ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.slots          ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.occupancy_logs ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.users, public.login_attempts, public.slots, public.occupancy_logs
  FROM anon, authenticated;
