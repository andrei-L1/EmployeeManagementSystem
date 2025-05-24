-- Add location columns to attendance_records table
ALTER TABLE attendance_records
ADD COLUMN clock_in_latitude DECIMAL(10,8) NULL AFTER photo_path,
ADD COLUMN clock_in_longitude DECIMAL(11,8) NULL AFTER clock_in_latitude,
ADD COLUMN clock_out_latitude DECIMAL(10,8) NULL AFTER clock_in_longitude,
ADD COLUMN clock_out_longitude DECIMAL(11,8) NULL AFTER clock_out_latitude; 