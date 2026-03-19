DELIMITER $$

-- usernek select és delete jogosultság szükséges a fodisz db-n
-- a 2 db-nek ugyanazon a szerveren kell lennie
CREATE TRIGGER delete_calendar_event
AFTER DELETE ON fodisz_vespa.vespa_contests
FOR EACH ROW
BEGIN
    DECLARE calendar_event_id INT;

    -- event keresése
    SELECT tec_id INTO calendar_event_id
    FROM fodisz_hu.wpkt_vespa_competition_ids
    WHERE vespa_id = OLD.contest_id;

    -- ha létezik a calendar event töröljük
    IF calendar_event_id IS NOT NULL THEN
        DELETE FROM fodisz_hu.wpkt_tec_events WHERE post_id = calendar_event_id;
        DELETE FROM fodisz_hu.wpkt_tec_occurrences WHERE post_id = calendar_event_id;
        DELETE FROM fodisz_hu.wpkt_posts WHERE ID = calendar_event_id AND post_type='tribe_events';;
    END IF;
END$$

DELIMITER ;



DELIMITER $$
CREATE TRIGGER update_calendar_event
AFTER UPDATE ON fodisz_vespa.vespa_contests
FOR EACH ROW
BEGIN
    DECLARE calendar_event_id INT;

    -- event keresése
    SELECT tec_id INTO calendar_event_id
    FROM fodisz_hu.wpkt_vespa_competition_ids
    WHERE vespa_id = OLD.contest_id;

    -- ha létezik a calendar event frissitjük
    IF calendar_event_id IS NOT NULL THEN
        UPDATE fodisz_hu.wpkt_tec_events
        SET `start_date` = DATE_FORMAT(NEW.start_at, '%Y-%m-%d %H:%i:%s'), 
            end_date = DATE_FORMAT(NEW.end_at, '%Y-%m-%d %H:%i:%s'),
            start_date_utc = DATE_FORMAT(CONVERT_TZ(NEW.start_at, @@session.time_zone, '+00:00'), '%Y-%m-%d %H:%i:%s'),
            end_date_utc = DATE_FORMAT(CONVERT_TZ(NEW.end_at, @@session.time_zone, '+00:00'), '%Y-%m-%d %H:%i:%s')
        WHERE post_id = calendar_event_id;

        UPDATE fodisz_hu.wpkt_tec_occurrences
        SET `start_date` = DATE_FORMAT(NEW.start_at, '%Y-%m-%d %H:%i:%s'), 
            end_date = DATE_FORMAT(NEW.end_at, '%Y-%m-%d %H:%i:%s'),
            start_date_utc = DATE_FORMAT(CONVERT_TZ(NEW.start_at, @@session.time_zone, '+00:00'), '%Y-%m-%d %H:%i:%s'),
            end_date_utc = DATE_FORMAT(CONVERT_TZ(NEW.end_at, @@session.time_zone, '+00:00'), '%Y-%m-%d %H:%i:%s')
        WHERE post_id = calendar_event_id;

        UPDATE fodisz_hu.wpkt_posts
        SET post_name = NEW.contest_name
        WHERE ID = calendar_event_id AND post_type='tribe_events';
    END IF;
END$$

DELIMITER ;