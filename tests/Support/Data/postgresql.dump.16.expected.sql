CREATE SEQUENCE IF NOT EXISTS info_id_seq START WITH 1 INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 CACHE 1 NO CYCLE;
CREATE SEQUENCE IF NOT EXISTS owner_id_seq START WITH 1 INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 CACHE 1 NO CYCLE;
CREATE SEQUENCE IF NOT EXISTS pet_id_seq START WITH 1 INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 CACHE 1 NO CYCLE;
CREATE OR REPLACE FUNCTION public.before_owner_update_func()
    RETURNS trigger
 LANGUAGE plpgsql
AS $function$
BEGIN
    IF NEW.first_name = 'John' THEN
        NEW.last_name := 'Doe';
    END IF;
    RETURN NEW;
END;
$function$
;
CREATE OR REPLACE FUNCTION public.get_full_name(owner_id_param integer)
    RETURNS character varying
    LANGUAGE plpgsql
AS $function$
DECLARE
    full_name varchar(150);
BEGIN
    SELECT concat_ws(' ', first_name, last_name)
    INTO full_name
    FROM owner
    WHERE id = owner_id_param;
    RETURN full_name;
END;
$function$
;
CREATE OR REPLACE PROCEDURE public.rename_to_john_doe(IN owner_id_param integer)
    LANGUAGE sql
AS $procedure$
UPDATE owner SET first_name = 'John', last_name = 'Doe' WHERE id = owner_id_param;
$procedure$
;
CREATE TABLE IF NOT EXISTS info (id integer DEFAULT nextval('info_id_seq'::regclass) NOT NULL, name character varying(255) NOT NULL);
CREATE TABLE IF NOT EXISTS owner (id integer DEFAULT nextval('owner_id_seq'::regclass) NOT NULL, first_name character varying(50) NOT NULL, last_name character varying(100) NOT NULL, code character(8) NOT NULL);
CREATE TABLE IF NOT EXISTS pet (id integer DEFAULT nextval('pet_id_seq'::regclass) NOT NULL, type USER-DEFINED NOT NULL, info_id integer NOT NULL, owner_id integer NOT NULL);
CREATE TABLE IF NOT EXISTS vw_pet (id integer, type USER-DEFINED);
ALTER TABLE public.info ADD CONSTRAINT info_pkey PRIMARY KEY (id);
ALTER TABLE public.owner ADD CONSTRAINT idx_name UNIQUE (first_name, last_name);
ALTER TABLE public.owner ADD CONSTRAINT owner_pkey PRIMARY KEY (id);
ALTER TABLE public.pet ADD CONSTRAINT fk_info FOREIGN KEY (info_id) REFERENCES info(id) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE public.pet ADD CONSTRAINT fk_owner FOREIGN KEY (owner_id) REFERENCES owner(id) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE public.pet ADD CONSTRAINT pet_pkey PRIMARY KEY (id);
CREATE INDEX idx_first_name ON public.owner USING btree (first_name);
CREATE INDEX idx_last_name ON public.owner USING btree (last_name);
CREATE UNIQUE INDEX idx_name ON public.owner USING btree (first_name, last_name);
CREATE OR REPLACE VIEW vw_pet AS  SELECT id,
                                         type
                                  FROM pet;
CREATE TRIGGER before_owner_update BEFORE UPDATE ON public.owner FOR EACH ROW EXECUTE FUNCTION before_owner_update_func();
