-- ============================================================================
--  Jackisa Store - category attribute coverage (v3)
--  Category-specific spec forms for every category still lacking them, so a
--  listing form is never generic. Additive and idempotent; never edits v1/v2.
-- ============================================================================
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

INSERT INTO `store_attribute_defs`
  (`category_id`,`kind`,`field_key`,`label`,`field_type`,`options`,`unit`,`placeholder`,`is_required`,`sort_order`)
SELECT c.id, s.kind, s.field_key, s.label, s.field_type, s.options, s.unit, s.placeholder, s.is_required, s.sort_order
FROM (
SELECT 'health-wellness' AS slug,'product' AS kind,'brand' AS field_key,'Brand' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'health-wellness' AS slug,'product' AS kind,'dosage' AS field_key,'Dosage' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'health-wellness' AS slug,'product' AS kind,'expiry' AS field_key,'Expiry date' AS label,'date' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'health-wellness' AS slug,'product' AS kind,'certification' AS field_key,'Certification' AS label,'select' AS field_type,'["None", "FDA", "UNBS", "ISO"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'baby-kids' AS slug,'product' AS kind,'brand' AS field_key,'Brand' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'baby-kids' AS slug,'product' AS kind,'age_range' AS field_key,'Age range' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,20 AS sort_order
UNION ALL
SELECT 'baby-kids' AS slug,'product' AS kind,'material' AS field_key,'Material' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'baby-kids' AS slug,'product' AS kind,'safety' AS field_key,'Safety standard' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'beverages' AS slug,'product' AS kind,'brand' AS field_key,'Brand' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'beverages' AS slug,'product' AS kind,'volume' AS field_key,'Volume' AS label,'number' AS field_type,NULL AS options,'ml' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'beverages' AS slug,'product' AS kind,'pack_size' AS field_key,'Pack size' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'beverages' AS slug,'product' AS kind,'expiry' AS field_key,'Best before' AS label,'date' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'music-instruments' AS slug,'product' AS kind,'brand' AS field_key,'Brand' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'music-instruments' AS slug,'product' AS kind,'instrument_type' AS field_key,'Instrument type' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,20 AS sort_order
UNION ALL
SELECT 'music-instruments' AS slug,'product' AS kind,'skill_level' AS field_key,'Skill level' AS label,'select' AS field_type,'["Beginner", "Intermediate", "Professional"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'sports-outdoors' AS slug,'product' AS kind,'brand' AS field_key,'Brand' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'sports-outdoors' AS slug,'product' AS kind,'sport' AS field_key,'Sport' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'sports-outdoors' AS slug,'product' AS kind,'size' AS field_key,'Size' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'sports-outdoors' AS slug,'product' AS kind,'material' AS field_key,'Material' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'toys-games' AS slug,'product' AS kind,'brand' AS field_key,'Brand' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'toys-games' AS slug,'product' AS kind,'age_range' AS field_key,'Recommended age' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,20 AS sort_order
UNION ALL
SELECT 'toys-games' AS slug,'product' AS kind,'material' AS field_key,'Material' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'toys-games' AS slug,'product' AS kind,'battery' AS field_key,'Battery required' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'tools-hardware' AS slug,'product' AS kind,'brand' AS field_key,'Brand' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'tools-hardware' AS slug,'product' AS kind,'power_source' AS field_key,'Power source' AS label,'select' AS field_type,'["Manual", "Electric", "Battery", "Petrol"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'tools-hardware' AS slug,'product' AS kind,'material' AS field_key,'Material' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'art-collectibles' AS slug,'product' AS kind,'artist' AS field_key,'Artist' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'art-collectibles' AS slug,'product' AS kind,'medium' AS field_key,'Medium' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'art-collectibles' AS slug,'product' AS kind,'dimensions' AS field_key,'Dimensions' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'art-collectibles' AS slug,'product' AS kind,'signed' AS field_key,'Signed' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'pet-supplies' AS slug,'product' AS kind,'brand' AS field_key,'Brand' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'pet-supplies' AS slug,'product' AS kind,'pet_type' AS field_key,'Pet type' AS label,'select' AS field_type,'["Dog", "Cat", "Bird", "Fish", "Other"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'pet-supplies' AS slug,'product' AS kind,'weight' AS field_key,'Weight' AS label,'number' AS field_type,NULL AS options,'g' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'garden-outdoor' AS slug,'product' AS kind,'brand' AS field_key,'Brand' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'garden-outdoor' AS slug,'product' AS kind,'plant_type' AS field_key,'Type' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'garden-outdoor' AS slug,'product' AS kind,'sunlight' AS field_key,'Sunlight' AS label,'select' AS field_type,'["Full sun", "Partial shade", "Full shade"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'garden-outdoor' AS slug,'product' AS kind,'pot_size' AS field_key,'Pot size' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'office-business' AS slug,'product' AS kind,'brand' AS field_key,'Brand' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'office-business' AS slug,'product' AS kind,'material' AS field_key,'Material' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'office-business' AS slug,'product' AS kind,'dimensions' AS field_key,'Dimensions' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'office-business' AS slug,'product' AS kind,'colour' AS field_key,'Colour' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'photography-video-gear' AS slug,'product' AS kind,'brand' AS field_key,'Brand' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'photography-video-gear' AS slug,'product' AS kind,'sensor' AS field_key,'Sensor' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'photography-video-gear' AS slug,'product' AS kind,'mount' AS field_key,'Mount' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'photography-video-gear' AS slug,'product' AS kind,'condition' AS field_key,'Condition' AS label,'select' AS field_type,'["New", "Used", "Refurbished"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'gaming-consoles' AS slug,'product' AS kind,'platform' AS field_key,'Platform' AS label,'select' AS field_type,'["PS5", "Xbox", "Nintendo", "PC", "Retro"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'gaming-consoles' AS slug,'product' AS kind,'condition' AS field_key,'Condition' AS label,'select' AS field_type,'["New", "Used", "Refurbished"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'gaming-consoles' AS slug,'product' AS kind,'region' AS field_key,'Region' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'handmade-crafts' AS slug,'product' AS kind,'artisan' AS field_key,'Artisan' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'handmade-crafts' AS slug,'product' AS kind,'material' AS field_key,'Material' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'handmade-crafts' AS slug,'product' AS kind,'dimensions' AS field_key,'Dimensions' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'handmade-crafts' AS slug,'product' AS kind,'made_to_order' AS field_key,'Made to order' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'digital-downloads' AS slug,'product' AS kind,'file_format' AS field_key,'File format' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'digital-downloads' AS slug,'product' AS kind,'file_size' AS field_key,'File size' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'digital-downloads' AS slug,'product' AS kind,'licence' AS field_key,'Licence' AS label,'select' AS field_type,'["Personal", "Commercial", "Extended"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'software-licences' AS slug,'product' AS kind,'platform' AS field_key,'Platform' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'software-licences' AS slug,'product' AS kind,'licence_term' AS field_key,'Licence term' AS label,'select' AS field_type,'["Monthly", "Yearly", "Perpetual"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'software-licences' AS slug,'product' AS kind,'seats' AS field_key,'Seats' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'gift-cards-vouchers' AS slug,'product' AS kind,'value' AS field_key,'Card value' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'gift-cards-vouchers' AS slug,'product' AS kind,'validity' AS field_key,'Validity' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'gift-cards-vouchers' AS slug,'product' AS kind,'redeemable_at' AS field_key,'Redeemable at' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'web-app-development' AS slug,'service' AS kind,'stack' AS field_key,'Technology stack' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'web-app-development' AS slug,'service' AS kind,'engagement' AS field_key,'Engagement' AS label,'select' AS field_type,'["Fixed price", "Hourly", "Retainer", "Milestones"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'web-app-development' AS slug,'service' AS kind,'experience_years' AS field_key,'Years of experience' AS label,'number' AS field_type,NULL AS options,'yrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'web-app-development' AS slug,'service' AS kind,'portfolio_url' AS field_key,'Portfolio URL' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'svc-data-analytics' AS slug,'service' AS kind,'stack' AS field_key,'Technology stack' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-data-analytics' AS slug,'service' AS kind,'engagement' AS field_key,'Engagement' AS label,'select' AS field_type,'["Fixed price", "Hourly", "Retainer", "Milestones"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-data-analytics' AS slug,'service' AS kind,'experience_years' AS field_key,'Years of experience' AS label,'number' AS field_type,NULL AS options,'yrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-data-analytics' AS slug,'service' AS kind,'portfolio_url' AS field_key,'Portfolio URL' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'svc-cybersecurity' AS slug,'service' AS kind,'stack' AS field_key,'Technology stack' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-cybersecurity' AS slug,'service' AS kind,'engagement' AS field_key,'Engagement' AS label,'select' AS field_type,'["Fixed price", "Hourly", "Retainer", "Milestones"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-cybersecurity' AS slug,'service' AS kind,'experience_years' AS field_key,'Years of experience' AS label,'number' AS field_type,NULL AS options,'yrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-cybersecurity' AS slug,'service' AS kind,'portfolio_url' AS field_key,'Portfolio URL' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'svc-it-support' AS slug,'service' AS kind,'stack' AS field_key,'Technology stack' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-it-support' AS slug,'service' AS kind,'engagement' AS field_key,'Engagement' AS label,'select' AS field_type,'["Fixed price", "Hourly", "Retainer", "Milestones"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-it-support' AS slug,'service' AS kind,'experience_years' AS field_key,'Years of experience' AS label,'number' AS field_type,NULL AS options,'yrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-it-support' AS slug,'service' AS kind,'portfolio_url' AS field_key,'Portfolio URL' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'svc-devops-cloud' AS slug,'service' AS kind,'stack' AS field_key,'Technology stack' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-devops-cloud' AS slug,'service' AS kind,'engagement' AS field_key,'Engagement' AS label,'select' AS field_type,'["Fixed price", "Hourly", "Retainer", "Milestones"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-devops-cloud' AS slug,'service' AS kind,'experience_years' AS field_key,'Years of experience' AS label,'number' AS field_type,NULL AS options,'yrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-devops-cloud' AS slug,'service' AS kind,'portfolio_url' AS field_key,'Portfolio URL' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'svc-api-integrations' AS slug,'service' AS kind,'stack' AS field_key,'Technology stack' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-api-integrations' AS slug,'service' AS kind,'engagement' AS field_key,'Engagement' AS label,'select' AS field_type,'["Fixed price", "Hourly", "Retainer", "Milestones"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-api-integrations' AS slug,'service' AS kind,'experience_years' AS field_key,'Years of experience' AS label,'number' AS field_type,NULL AS options,'yrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-api-integrations' AS slug,'service' AS kind,'portfolio_url' AS field_key,'Portfolio URL' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'svc-website-development' AS slug,'service' AS kind,'stack' AS field_key,'Technology stack' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-website-development' AS slug,'service' AS kind,'engagement' AS field_key,'Engagement' AS label,'select' AS field_type,'["Fixed price", "Hourly", "Retainer", "Milestones"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-website-development' AS slug,'service' AS kind,'experience_years' AS field_key,'Years of experience' AS label,'number' AS field_type,NULL AS options,'yrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-website-development' AS slug,'service' AS kind,'portfolio_url' AS field_key,'Portfolio URL' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'svc-mobile-app-development' AS slug,'service' AS kind,'stack' AS field_key,'Technology stack' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-mobile-app-development' AS slug,'service' AS kind,'engagement' AS field_key,'Engagement' AS label,'select' AS field_type,'["Fixed price", "Hourly", "Retainer", "Milestones"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-mobile-app-development' AS slug,'service' AS kind,'experience_years' AS field_key,'Years of experience' AS label,'number' AS field_type,NULL AS options,'yrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-mobile-app-development' AS slug,'service' AS kind,'portfolio_url' AS field_key,'Portfolio URL' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'svc-web-maintenance' AS slug,'service' AS kind,'stack' AS field_key,'Technology stack' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-web-maintenance' AS slug,'service' AS kind,'engagement' AS field_key,'Engagement' AS label,'select' AS field_type,'["Fixed price", "Hourly", "Retainer", "Milestones"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-web-maintenance' AS slug,'service' AS kind,'experience_years' AS field_key,'Years of experience' AS label,'number' AS field_type,NULL AS options,'yrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-web-maintenance' AS slug,'service' AS kind,'portfolio_url' AS field_key,'Portfolio URL' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'svc-web-applications' AS slug,'service' AS kind,'stack' AS field_key,'Technology stack' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-web-applications' AS slug,'service' AS kind,'engagement' AS field_key,'Engagement' AS label,'select' AS field_type,'["Fixed price", "Hourly", "Retainer", "Milestones"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-web-applications' AS slug,'service' AS kind,'experience_years' AS field_key,'Years of experience' AS label,'number' AS field_type,NULL AS options,'yrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-web-applications' AS slug,'service' AS kind,'portfolio_url' AS field_key,'Portfolio URL' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'svc-landing-pages' AS slug,'service' AS kind,'stack' AS field_key,'Technology stack' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-landing-pages' AS slug,'service' AS kind,'engagement' AS field_key,'Engagement' AS label,'select' AS field_type,'["Fixed price", "Hourly", "Retainer", "Milestones"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-landing-pages' AS slug,'service' AS kind,'experience_years' AS field_key,'Years of experience' AS label,'number' AS field_type,NULL AS options,'yrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-landing-pages' AS slug,'service' AS kind,'portfolio_url' AS field_key,'Portfolio URL' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'svc-ecommerce-stores' AS slug,'service' AS kind,'stack' AS field_key,'Technology stack' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-ecommerce-stores' AS slug,'service' AS kind,'engagement' AS field_key,'Engagement' AS label,'select' AS field_type,'["Fixed price", "Hourly", "Retainer", "Milestones"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-ecommerce-stores' AS slug,'service' AS kind,'experience_years' AS field_key,'Years of experience' AS label,'number' AS field_type,NULL AS options,'yrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-ecommerce-stores' AS slug,'service' AS kind,'portfolio_url' AS field_key,'Portfolio URL' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'svc-ui-ux-design' AS slug,'service' AS kind,'deliverable' AS field_key,'Deliverables' AS label,'multiselect' AS field_type,'["Concept", "Final design", "Brand guide", "Source files"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-ui-ux-design' AS slug,'service' AS kind,'revisions' AS field_key,'Revisions included' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-ui-ux-design' AS slug,'service' AS kind,'file_formats' AS field_key,'File formats' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-print-packaging' AS slug,'service' AS kind,'deliverable' AS field_key,'Deliverables' AS label,'multiselect' AS field_type,'["Concept", "Final design", "Brand guide", "Source files"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-print-packaging' AS slug,'service' AS kind,'revisions' AS field_key,'Revisions included' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-print-packaging' AS slug,'service' AS kind,'file_formats' AS field_key,'File formats' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-brand-identity' AS slug,'service' AS kind,'deliverable' AS field_key,'Deliverables' AS label,'multiselect' AS field_type,'["Concept", "Final design", "Brand guide", "Source files"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-brand-identity' AS slug,'service' AS kind,'revisions' AS field_key,'Revisions included' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-brand-identity' AS slug,'service' AS kind,'file_formats' AS field_key,'File formats' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-logo-design' AS slug,'service' AS kind,'deliverable' AS field_key,'Deliverables' AS label,'multiselect' AS field_type,'["Concept", "Final design", "Brand guide", "Source files"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-logo-design' AS slug,'service' AS kind,'revisions' AS field_key,'Revisions included' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-logo-design' AS slug,'service' AS kind,'file_formats' AS field_key,'File formats' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-email-marketing' AS slug,'service' AS kind,'channel' AS field_key,'Channel' AS label,'select' AS field_type,'["Email", "Social", "Search", "Paid ads", "Influencer", "Mixed"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-email-marketing' AS slug,'service' AS kind,'duration_weeks' AS field_key,'Minimum duration' AS label,'number' AS field_type,NULL AS options,'weeks' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-email-marketing' AS slug,'service' AS kind,'reporting' AS field_key,'Reporting' AS label,'select' AS field_type,'["Weekly", "Monthly", "End of campaign"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-social-media' AS slug,'service' AS kind,'channel' AS field_key,'Channel' AS label,'select' AS field_type,'["Email", "Social", "Search", "Paid ads", "Influencer", "Mixed"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-social-media' AS slug,'service' AS kind,'duration_weeks' AS field_key,'Minimum duration' AS label,'number' AS field_type,NULL AS options,'weeks' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-social-media' AS slug,'service' AS kind,'reporting' AS field_key,'Reporting' AS label,'select' AS field_type,'["Weekly", "Monthly", "End of campaign"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-paid-ads' AS slug,'service' AS kind,'channel' AS field_key,'Channel' AS label,'select' AS field_type,'["Email", "Social", "Search", "Paid ads", "Influencer", "Mixed"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-paid-ads' AS slug,'service' AS kind,'duration_weeks' AS field_key,'Minimum duration' AS label,'number' AS field_type,NULL AS options,'weeks' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-paid-ads' AS slug,'service' AS kind,'reporting' AS field_key,'Reporting' AS label,'select' AS field_type,'["Weekly", "Monthly", "End of campaign"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-seo' AS slug,'service' AS kind,'channel' AS field_key,'Channel' AS label,'select' AS field_type,'["Email", "Social", "Search", "Paid ads", "Influencer", "Mixed"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-seo' AS slug,'service' AS kind,'duration_weeks' AS field_key,'Minimum duration' AS label,'number' AS field_type,NULL AS options,'weeks' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-seo' AS slug,'service' AS kind,'reporting' AS field_key,'Reporting' AS label,'select' AS field_type,'["Weekly", "Monthly", "End of campaign"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-influencer-marketing' AS slug,'service' AS kind,'channel' AS field_key,'Channel' AS label,'select' AS field_type,'["Email", "Social", "Search", "Paid ads", "Influencer", "Mixed"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-influencer-marketing' AS slug,'service' AS kind,'duration_weeks' AS field_key,'Minimum duration' AS label,'number' AS field_type,NULL AS options,'weeks' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-influencer-marketing' AS slug,'service' AS kind,'reporting' AS field_key,'Reporting' AS label,'select' AS field_type,'["Weekly", "Monthly", "End of campaign"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-media-buying' AS slug,'service' AS kind,'channel' AS field_key,'Channel' AS label,'select' AS field_type,'["Email", "Social", "Search", "Paid ads", "Influencer", "Mixed"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-media-buying' AS slug,'service' AS kind,'duration_weeks' AS field_key,'Minimum duration' AS label,'number' AS field_type,NULL AS options,'weeks' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-media-buying' AS slug,'service' AS kind,'reporting' AS field_key,'Reporting' AS label,'select' AS field_type,'["Weekly", "Monthly", "End of campaign"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-brand-campaigns' AS slug,'service' AS kind,'channel' AS field_key,'Channel' AS label,'select' AS field_type,'["Email", "Social", "Search", "Paid ads", "Influencer", "Mixed"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-brand-campaigns' AS slug,'service' AS kind,'duration_weeks' AS field_key,'Minimum duration' AS label,'number' AS field_type,NULL AS options,'weeks' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-brand-campaigns' AS slug,'service' AS kind,'reporting' AS field_key,'Reporting' AS label,'select' AS field_type,'["Weekly", "Monthly", "End of campaign"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-market-research' AS slug,'service' AS kind,'channel' AS field_key,'Channel' AS label,'select' AS field_type,'["Email", "Social", "Search", "Paid ads", "Influencer", "Mixed"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-market-research' AS slug,'service' AS kind,'duration_weeks' AS field_key,'Minimum duration' AS label,'number' AS field_type,NULL AS options,'weeks' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-market-research' AS slug,'service' AS kind,'reporting' AS field_key,'Reporting' AS label,'select' AS field_type,'["Weekly", "Monthly", "End of campaign"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-transcription' AS slug,'service' AS kind,'language_pair' AS field_key,'Language pair' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-transcription' AS slug,'service' AS kind,'word_count' AS field_key,'Word count' AS label,'number' AS field_type,NULL AS options,'words' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-transcription' AS slug,'service' AS kind,'turnaround_days' AS field_key,'Turnaround' AS label,'number' AS field_type,NULL AS options,'days' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-transcription' AS slug,'service' AS kind,'deliverable' AS field_key,'Deliverable' AS label,'select' AS field_type,'["Document", "Subtitles", "Audio", "Spreadsheet"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'svc-translation' AS slug,'service' AS kind,'language_pair' AS field_key,'Language pair' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-translation' AS slug,'service' AS kind,'word_count' AS field_key,'Word count' AS label,'number' AS field_type,NULL AS options,'words' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-translation' AS slug,'service' AS kind,'turnaround_days' AS field_key,'Turnaround' AS label,'number' AS field_type,NULL AS options,'days' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-translation' AS slug,'service' AS kind,'deliverable' AS field_key,'Deliverable' AS label,'select' AS field_type,'["Document", "Subtitles", "Audio", "Spreadsheet"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'svc-proofreading' AS slug,'service' AS kind,'language_pair' AS field_key,'Language pair' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-proofreading' AS slug,'service' AS kind,'word_count' AS field_key,'Word count' AS label,'number' AS field_type,NULL AS options,'words' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-proofreading' AS slug,'service' AS kind,'turnaround_days' AS field_key,'Turnaround' AS label,'number' AS field_type,NULL AS options,'days' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-proofreading' AS slug,'service' AS kind,'deliverable' AS field_key,'Deliverable' AS label,'select' AS field_type,'["Document", "Subtitles", "Audio", "Spreadsheet"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'svc-copywriting' AS slug,'service' AS kind,'language_pair' AS field_key,'Language pair' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-copywriting' AS slug,'service' AS kind,'word_count' AS field_key,'Word count' AS label,'number' AS field_type,NULL AS options,'words' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-copywriting' AS slug,'service' AS kind,'turnaround_days' AS field_key,'Turnaround' AS label,'number' AS field_type,NULL AS options,'days' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-copywriting' AS slug,'service' AS kind,'deliverable' AS field_key,'Deliverable' AS label,'select' AS field_type,'["Document", "Subtitles", "Audio", "Spreadsheet"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,40 AS sort_order
UNION ALL
SELECT 'svc-drone-coverage' AS slug,'service' AS kind,'deliverable' AS field_key,'Deliverables' AS label,'multiselect' AS field_type,'["Edited photos", "Raw files", "Highlight video", "Full video"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-drone-coverage' AS slug,'service' AS kind,'duration_hours' AS field_key,'Session length' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-drone-coverage' AS slug,'service' AS kind,'editing_days' AS field_key,'Editing turnaround' AS label,'number' AS field_type,NULL AS options,'days' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-videography' AS slug,'service' AS kind,'deliverable' AS field_key,'Deliverables' AS label,'multiselect' AS field_type,'["Edited photos", "Raw files", "Highlight video", "Full video"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-videography' AS slug,'service' AS kind,'duration_hours' AS field_key,'Session length' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-videography' AS slug,'service' AS kind,'editing_days' AS field_key,'Editing turnaround' AS label,'number' AS field_type,NULL AS options,'days' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-product-photography' AS slug,'service' AS kind,'deliverable' AS field_key,'Deliverables' AS label,'multiselect' AS field_type,'["Edited photos", "Raw files", "Highlight video", "Full video"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-product-photography' AS slug,'service' AS kind,'duration_hours' AS field_key,'Session length' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-product-photography' AS slug,'service' AS kind,'editing_days' AS field_key,'Editing turnaround' AS label,'number' AS field_type,NULL AS options,'days' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-event-photography' AS slug,'service' AS kind,'deliverable' AS field_key,'Deliverables' AS label,'multiselect' AS field_type,'["Edited photos", "Raw files", "Highlight video", "Full video"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-event-photography' AS slug,'service' AS kind,'duration_hours' AS field_key,'Session length' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-event-photography' AS slug,'service' AS kind,'editing_days' AS field_key,'Editing turnaround' AS label,'number' AS field_type,NULL AS options,'days' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-wedding-photography' AS slug,'service' AS kind,'deliverable' AS field_key,'Deliverables' AS label,'multiselect' AS field_type,'["Edited photos", "Raw files", "Highlight video", "Full video"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-wedding-photography' AS slug,'service' AS kind,'duration_hours' AS field_key,'Session length' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-wedding-photography' AS slug,'service' AS kind,'editing_days' AS field_key,'Editing turnaround' AS label,'number' AS field_type,NULL AS options,'days' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-music-lessons' AS slug,'service' AS kind,'subject' AS field_key,'Subject' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-music-lessons' AS slug,'service' AS kind,'level' AS field_key,'Level' AS label,'select' AS field_type,'["Primary", "Secondary", "University", "Adult"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-music-lessons' AS slug,'service' AS kind,'session_type' AS field_key,'Session type' AS label,'select' AS field_type,'["One-on-one", "Group", "Online", "In person"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-language-tutoring' AS slug,'service' AS kind,'subject' AS field_key,'Subject' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-language-tutoring' AS slug,'service' AS kind,'level' AS field_key,'Level' AS label,'select' AS field_type,'["Primary", "Secondary", "University", "Adult"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-language-tutoring' AS slug,'service' AS kind,'session_type' AS field_key,'Session type' AS label,'select' AS field_type,'["One-on-one", "Group", "Online", "In person"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-university-tutoring' AS slug,'service' AS kind,'subject' AS field_key,'Subject' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-university-tutoring' AS slug,'service' AS kind,'level' AS field_key,'Level' AS label,'select' AS field_type,'["Primary", "Secondary", "University", "Adult"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-university-tutoring' AS slug,'service' AS kind,'session_type' AS field_key,'Session type' AS label,'select' AS field_type,'["One-on-one", "Group", "Online", "In person"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-secondary-tutoring' AS slug,'service' AS kind,'subject' AS field_key,'Subject' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-secondary-tutoring' AS slug,'service' AS kind,'level' AS field_key,'Level' AS label,'select' AS field_type,'["Primary", "Secondary", "University", "Adult"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-secondary-tutoring' AS slug,'service' AS kind,'session_type' AS field_key,'Session type' AS label,'select' AS field_type,'["One-on-one", "Group", "Online", "In person"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-primary-tutoring' AS slug,'service' AS kind,'subject' AS field_key,'Subject' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-primary-tutoring' AS slug,'service' AS kind,'level' AS field_key,'Level' AS label,'select' AS field_type,'["Primary", "Secondary", "University", "Adult"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-primary-tutoring' AS slug,'service' AS kind,'session_type' AS field_key,'Session type' AS label,'select' AS field_type,'["One-on-one", "Group", "Online", "In person"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-leadership-training' AS slug,'service' AS kind,'subject' AS field_key,'Subject' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-leadership-training' AS slug,'service' AS kind,'level' AS field_key,'Level' AS label,'select' AS field_type,'["Primary", "Secondary", "University", "Adult"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-leadership-training' AS slug,'service' AS kind,'session_type' AS field_key,'Session type' AS label,'select' AS field_type,'["One-on-one", "Group", "Online", "In person"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-life-coaching' AS slug,'service' AS kind,'subject' AS field_key,'Subject' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-life-coaching' AS slug,'service' AS kind,'level' AS field_key,'Level' AS label,'select' AS field_type,'["Primary", "Secondary", "University", "Adult"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-life-coaching' AS slug,'service' AS kind,'session_type' AS field_key,'Session type' AS label,'select' AS field_type,'["One-on-one", "Group", "Online", "In person"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-career-coaching' AS slug,'service' AS kind,'subject' AS field_key,'Subject' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-career-coaching' AS slug,'service' AS kind,'level' AS field_key,'Level' AS label,'select' AS field_type,'["Primary", "Secondary", "University", "Adult"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-career-coaching' AS slug,'service' AS kind,'session_type' AS field_key,'Session type' AS label,'select' AS field_type,'["One-on-one", "Group", "Online", "In person"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-business-coaching' AS slug,'service' AS kind,'subject' AS field_key,'Subject' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-business-coaching' AS slug,'service' AS kind,'level' AS field_key,'Level' AS label,'select' AS field_type,'["Primary", "Secondary", "University", "Adult"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-business-coaching' AS slug,'service' AS kind,'session_type' AS field_key,'Session type' AS label,'select' AS field_type,'["One-on-one", "Group", "Online", "In person"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-decor-setup' AS slug,'service' AS kind,'event_type' AS field_key,'Event type' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-decor-setup' AS slug,'service' AS kind,'duration_hours' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-decor-setup' AS slug,'service' AS kind,'people' AS field_key,'People covered' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-mcs-hosts' AS slug,'service' AS kind,'event_type' AS field_key,'Event type' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-mcs-hosts' AS slug,'service' AS kind,'duration_hours' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-mcs-hosts' AS slug,'service' AS kind,'people' AS field_key,'People covered' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-dj-sound' AS slug,'service' AS kind,'event_type' AS field_key,'Event type' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-dj-sound' AS slug,'service' AS kind,'duration_hours' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-dj-sound' AS slug,'service' AS kind,'people' AS field_key,'People covered' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-corporate-events' AS slug,'service' AS kind,'event_type' AS field_key,'Event type' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-corporate-events' AS slug,'service' AS kind,'duration_hours' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-corporate-events' AS slug,'service' AS kind,'people' AS field_key,'People covered' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-weddings' AS slug,'service' AS kind,'event_type' AS field_key,'Event type' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-weddings' AS slug,'service' AS kind,'duration_hours' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-weddings' AS slug,'service' AS kind,'people' AS field_key,'People covered' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-physiotherapy' AS slug,'service' AS kind,'speciality' AS field_key,'Speciality' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-physiotherapy' AS slug,'service' AS kind,'license_number' AS field_key,'Practitioner licence no.' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-physiotherapy' AS slug,'service' AS kind,'modality' AS field_key,'Modality' AS label,'select' AS field_type,'["In person", "Telehealth", "Home visit"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-optometry' AS slug,'service' AS kind,'speciality' AS field_key,'Speciality' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-optometry' AS slug,'service' AS kind,'license_number' AS field_key,'Practitioner licence no.' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-optometry' AS slug,'service' AS kind,'modality' AS field_key,'Modality' AS label,'select' AS field_type,'["In person", "Telehealth", "Home visit"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-dental-care' AS slug,'service' AS kind,'speciality' AS field_key,'Speciality' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-dental-care' AS slug,'service' AS kind,'license_number' AS field_key,'Practitioner licence no.' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-dental-care' AS slug,'service' AS kind,'modality' AS field_key,'Modality' AS label,'select' AS field_type,'["In person", "Telehealth", "Home visit"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-general-practice' AS slug,'service' AS kind,'speciality' AS field_key,'Speciality' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-general-practice' AS slug,'service' AS kind,'license_number' AS field_key,'Practitioner licence no.' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-general-practice' AS slug,'service' AS kind,'modality' AS field_key,'Modality' AS label,'select' AS field_type,'["In person", "Telehealth", "Home visit"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-aromatherapy' AS slug,'service' AS kind,'treatment' AS field_key,'Service' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-aromatherapy' AS slug,'service' AS kind,'duration_minutes' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'min' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-aromatherapy' AS slug,'service' AS kind,'at_home' AS field_key,'Mobile service available' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-sauna-steam' AS slug,'service' AS kind,'treatment' AS field_key,'Service' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-sauna-steam' AS slug,'service' AS kind,'duration_minutes' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'min' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-sauna-steam' AS slug,'service' AS kind,'at_home' AS field_key,'Mobile service available' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-facial-treatments' AS slug,'service' AS kind,'treatment' AS field_key,'Service' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-facial-treatments' AS slug,'service' AS kind,'duration_minutes' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'min' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-facial-treatments' AS slug,'service' AS kind,'at_home' AS field_key,'Mobile service available' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-massage-therapy' AS slug,'service' AS kind,'treatment' AS field_key,'Service' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-massage-therapy' AS slug,'service' AS kind,'duration_minutes' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'min' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-massage-therapy' AS slug,'service' AS kind,'at_home' AS field_key,'Mobile service available' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-barbering' AS slug,'service' AS kind,'treatment' AS field_key,'Service' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-barbering' AS slug,'service' AS kind,'duration_minutes' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'min' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-barbering' AS slug,'service' AS kind,'at_home' AS field_key,'Mobile service available' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-spa-massage' AS slug,'service' AS kind,'treatment' AS field_key,'Service' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-spa-massage' AS slug,'service' AS kind,'duration_minutes' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'min' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-spa-massage' AS slug,'service' AS kind,'at_home' AS field_key,'Mobile service available' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-makeup' AS slug,'service' AS kind,'treatment' AS field_key,'Service' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-makeup' AS slug,'service' AS kind,'duration_minutes' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'min' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-makeup' AS slug,'service' AS kind,'at_home' AS field_key,'Mobile service available' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-nails' AS slug,'service' AS kind,'treatment' AS field_key,'Service' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-nails' AS slug,'service' AS kind,'duration_minutes' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'min' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-nails' AS slug,'service' AS kind,'at_home' AS field_key,'Mobile service available' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-hair-styling' AS slug,'service' AS kind,'treatment' AS field_key,'Service' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-hair-styling' AS slug,'service' AS kind,'duration_minutes' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'min' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-hair-styling' AS slug,'service' AS kind,'at_home' AS field_key,'Mobile service available' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-sports-coaching' AS slug,'service' AS kind,'focus' AS field_key,'Goal' AS label,'select' AS field_type,'["Weight loss", "Strength", "Endurance", "Rehab", "General"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-sports-coaching' AS slug,'service' AS kind,'session_type' AS field_key,'Session type' AS label,'select' AS field_type,'["One-on-one", "Group", "Online", "In person"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-yoga-pilates' AS slug,'service' AS kind,'focus' AS field_key,'Goal' AS label,'select' AS field_type,'["Weight loss", "Strength", "Endurance", "Rehab", "General"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-yoga-pilates' AS slug,'service' AS kind,'session_type' AS field_key,'Session type' AS label,'select' AS field_type,'["One-on-one", "Group", "Online", "In person"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-weight-loss' AS slug,'service' AS kind,'focus' AS field_key,'Goal' AS label,'select' AS field_type,'["Weight loss", "Strength", "Endurance", "Rehab", "General"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-weight-loss' AS slug,'service' AS kind,'session_type' AS field_key,'Session type' AS label,'select' AS field_type,'["One-on-one", "Group", "Online", "In person"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-strength-training' AS slug,'service' AS kind,'focus' AS field_key,'Goal' AS label,'select' AS field_type,'["Weight loss", "Strength", "Endurance", "Rehab", "General"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-strength-training' AS slug,'service' AS kind,'session_type' AS field_key,'Session type' AS label,'select' AS field_type,'["One-on-one", "Group", "Online", "In person"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-appliance-repair' AS slug,'service' AS kind,'speciality' AS field_key,'Speciality' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-appliance-repair' AS slug,'service' AS kind,'call_out' AS field_key,'Call-out fee' AS label,'select' AS field_type,'["Included", "Extra"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-appliance-repair' AS slug,'service' AS kind,'materials' AS field_key,'Materials included' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-painting' AS slug,'service' AS kind,'speciality' AS field_key,'Speciality' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-painting' AS slug,'service' AS kind,'call_out' AS field_key,'Call-out fee' AS label,'select' AS field_type,'["Included", "Extra"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-painting' AS slug,'service' AS kind,'materials' AS field_key,'Materials included' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-carpentry' AS slug,'service' AS kind,'speciality' AS field_key,'Speciality' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-carpentry' AS slug,'service' AS kind,'call_out' AS field_key,'Call-out fee' AS label,'select' AS field_type,'["Included", "Extra"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-carpentry' AS slug,'service' AS kind,'materials' AS field_key,'Materials included' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-electrical' AS slug,'service' AS kind,'speciality' AS field_key,'Speciality' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-electrical' AS slug,'service' AS kind,'call_out' AS field_key,'Call-out fee' AS label,'select' AS field_type,'["Included", "Extra"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-electrical' AS slug,'service' AS kind,'materials' AS field_key,'Materials included' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-plumbing' AS slug,'service' AS kind,'speciality' AS field_key,'Speciality' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-plumbing' AS slug,'service' AS kind,'call_out' AS field_key,'Call-out fee' AS label,'select' AS field_type,'["Included", "Extra"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-plumbing' AS slug,'service' AS kind,'materials' AS field_key,'Materials included' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-fumigation' AS slug,'service' AS kind,'speciality' AS field_key,'Speciality' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-fumigation' AS slug,'service' AS kind,'call_out' AS field_key,'Call-out fee' AS label,'select' AS field_type,'["Included", "Extra"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-fumigation' AS slug,'service' AS kind,'materials' AS field_key,'Materials included' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-laundry-ironing' AS slug,'service' AS kind,'speciality' AS field_key,'Speciality' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-laundry-ironing' AS slug,'service' AS kind,'call_out' AS field_key,'Call-out fee' AS label,'select' AS field_type,'["Included", "Extra"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-laundry-ironing' AS slug,'service' AS kind,'materials' AS field_key,'Materials included' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-deep-cleaning' AS slug,'service' AS kind,'speciality' AS field_key,'Speciality' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-deep-cleaning' AS slug,'service' AS kind,'call_out' AS field_key,'Call-out fee' AS label,'select' AS field_type,'["Included", "Extra"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-deep-cleaning' AS slug,'service' AS kind,'materials' AS field_key,'Materials included' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-office-cleaning' AS slug,'service' AS kind,'speciality' AS field_key,'Speciality' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-office-cleaning' AS slug,'service' AS kind,'call_out' AS field_key,'Call-out fee' AS label,'select' AS field_type,'["Included", "Extra"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-office-cleaning' AS slug,'service' AS kind,'materials' AS field_key,'Materials included' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-home-cleaning' AS slug,'service' AS kind,'speciality' AS field_key,'Speciality' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-home-cleaning' AS slug,'service' AS kind,'call_out' AS field_key,'Call-out fee' AS label,'select' AS field_type,'["Included", "Extra"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-home-cleaning' AS slug,'service' AS kind,'materials' AS field_key,'Materials included' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-litigation' AS slug,'service' AS kind,'matter_area' AS field_key,'Matter area' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-litigation' AS slug,'service' AS kind,'license_number' AS field_key,'Advocate licence no.' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-litigation' AS slug,'service' AS kind,'engagement' AS field_key,'Engagement' AS label,'select' AS field_type,'["Hourly", "Fixed fee", "Retainer"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-contracts' AS slug,'service' AS kind,'matter_area' AS field_key,'Matter area' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-contracts' AS slug,'service' AS kind,'license_number' AS field_key,'Advocate licence no.' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-contracts' AS slug,'service' AS kind,'engagement' AS field_key,'Engagement' AS label,'select' AS field_type,'["Hourly", "Fixed fee", "Retainer"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-company-registration' AS slug,'service' AS kind,'matter_area' AS field_key,'Matter area' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-company-registration' AS slug,'service' AS kind,'license_number' AS field_key,'Advocate licence no.' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-company-registration' AS slug,'service' AS kind,'engagement' AS field_key,'Engagement' AS label,'select' AS field_type,'["Hourly", "Fixed fee", "Retainer"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-conveyancing' AS slug,'service' AS kind,'matter_area' AS field_key,'Matter area' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-conveyancing' AS slug,'service' AS kind,'license_number' AS field_key,'Advocate licence no.' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-conveyancing' AS slug,'service' AS kind,'engagement' AS field_key,'Engagement' AS label,'select' AS field_type,'["Hourly", "Fixed fee", "Retainer"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-audit' AS slug,'service' AS kind,'scope' AS field_key,'Scope' AS label,'multiselect' AS field_type,'["Bookkeeping", "Payroll", "Tax filing", "Audit", "Advisory"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-audit' AS slug,'service' AS kind,'frequency' AS field_key,'Frequency' AS label,'select' AS field_type,'["One-off", "Monthly", "Quarterly", "Yearly"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-audit' AS slug,'service' AS kind,'regulator' AS field_key,'Regulator' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-payroll' AS slug,'service' AS kind,'scope' AS field_key,'Scope' AS label,'multiselect' AS field_type,'["Bookkeeping", "Payroll", "Tax filing", "Audit", "Advisory"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-payroll' AS slug,'service' AS kind,'frequency' AS field_key,'Frequency' AS label,'select' AS field_type,'["One-off", "Monthly", "Quarterly", "Yearly"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-payroll' AS slug,'service' AS kind,'regulator' AS field_key,'Regulator' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-tax-filing' AS slug,'service' AS kind,'scope' AS field_key,'Scope' AS label,'multiselect' AS field_type,'["Bookkeeping", "Payroll", "Tax filing", "Audit", "Advisory"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-tax-filing' AS slug,'service' AS kind,'frequency' AS field_key,'Frequency' AS label,'select' AS field_type,'["One-off", "Monthly", "Quarterly", "Yearly"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-tax-filing' AS slug,'service' AS kind,'regulator' AS field_key,'Regulator' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-bookkeeping' AS slug,'service' AS kind,'scope' AS field_key,'Scope' AS label,'multiselect' AS field_type,'["Bookkeeping", "Payroll", "Tax filing", "Audit", "Advisory"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-bookkeeping' AS slug,'service' AS kind,'frequency' AS field_key,'Frequency' AS label,'select' AS field_type,'["One-off", "Monthly", "Quarterly", "Yearly"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-bookkeeping' AS slug,'service' AS kind,'regulator' AS field_key,'Regulator' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-valuation' AS slug,'service' AS kind,'property_type' AS field_key,'Property type' AS label,'select' AS field_type,'["House", "Apartment", "Land", "Commercial"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-valuation' AS slug,'service' AS kind,'bedrooms' AS field_key,'Bedrooms' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-valuation' AS slug,'service' AS kind,'location' AS field_key,'Location' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-property-management' AS slug,'service' AS kind,'property_type' AS field_key,'Property type' AS label,'select' AS field_type,'["House", "Apartment", "Land", "Commercial"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-property-management' AS slug,'service' AS kind,'bedrooms' AS field_key,'Bedrooms' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-property-management' AS slug,'service' AS kind,'location' AS field_key,'Location' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-rentals' AS slug,'service' AS kind,'property_type' AS field_key,'Property type' AS label,'select' AS field_type,'["House", "Apartment", "Land", "Commercial"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-rentals' AS slug,'service' AS kind,'bedrooms' AS field_key,'Bedrooms' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-rentals' AS slug,'service' AS kind,'location' AS field_key,'Location' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-property-sales' AS slug,'service' AS kind,'property_type' AS field_key,'Property type' AS label,'select' AS field_type,'["House", "Apartment", "Land", "Commercial"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-property-sales' AS slug,'service' AS kind,'bedrooms' AS field_key,'Bedrooms' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-property-sales' AS slug,'service' AS kind,'location' AS field_key,'Location' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-warehousing' AS slug,'service' AS kind,'vehicle' AS field_key,'Vehicle' AS label,'select' AS field_type,'["Boda", "Car", "Van", "Truck", "Bicycle"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-warehousing' AS slug,'service' AS kind,'max_weight_kg' AS field_key,'Max weight' AS label,'number' AS field_type,NULL AS options,'kg' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-warehousing' AS slug,'service' AS kind,'coverage' AS field_key,'Coverage' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-moving-relocation' AS slug,'service' AS kind,'vehicle' AS field_key,'Vehicle' AS label,'select' AS field_type,'["Boda", "Car", "Van", "Truck", "Bicycle"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-moving-relocation' AS slug,'service' AS kind,'max_weight_kg' AS field_key,'Max weight' AS label,'number' AS field_type,NULL AS options,'kg' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-moving-relocation' AS slug,'service' AS kind,'coverage' AS field_key,'Coverage' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-courier-parcel' AS slug,'service' AS kind,'vehicle' AS field_key,'Vehicle' AS label,'select' AS field_type,'["Boda", "Car", "Van", "Truck", "Bicycle"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-courier-parcel' AS slug,'service' AS kind,'max_weight_kg' AS field_key,'Max weight' AS label,'number' AS field_type,NULL AS options,'kg' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-courier-parcel' AS slug,'service' AS kind,'coverage' AS field_key,'Coverage' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-truck-haulage' AS slug,'service' AS kind,'vehicle' AS field_key,'Vehicle' AS label,'select' AS field_type,'["Boda", "Car", "Van", "Truck", "Bicycle"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-truck-haulage' AS slug,'service' AS kind,'max_weight_kg' AS field_key,'Max weight' AS label,'number' AS field_type,NULL AS options,'kg' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-truck-haulage' AS slug,'service' AS kind,'coverage' AS field_key,'Coverage' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-boda-delivery' AS slug,'service' AS kind,'vehicle' AS field_key,'Vehicle' AS label,'select' AS field_type,'["Boda", "Car", "Van", "Truck", "Bicycle"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-boda-delivery' AS slug,'service' AS kind,'max_weight_kg' AS field_key,'Max weight' AS label,'number' AS field_type,NULL AS options,'kg' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-boda-delivery' AS slug,'service' AS kind,'coverage' AS field_key,'Coverage' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-private-chef' AS slug,'service' AS kind,'cuisine' AS field_key,'Cuisine' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-private-chef' AS slug,'service' AS kind,'serves' AS field_key,'Serves up to' AS label,'number' AS field_type,NULL AS options,'people' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-private-chef' AS slug,'service' AS kind,'dietary' AS field_key,'Dietary options' AS label,'multiselect' AS field_type,'["Vegetarian", "Vegan", "Gluten-free", "Halal", "Kosher"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-baking-pastry' AS slug,'service' AS kind,'cuisine' AS field_key,'Cuisine' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-baking-pastry' AS slug,'service' AS kind,'serves' AS field_key,'Serves up to' AS label,'number' AS field_type,NULL AS options,'people' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-baking-pastry' AS slug,'service' AS kind,'dietary' AS field_key,'Dietary options' AS label,'multiselect' AS field_type,'["Vegetarian", "Vegan", "Gluten-free", "Halal", "Kosher"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-meal-prep' AS slug,'service' AS kind,'cuisine' AS field_key,'Cuisine' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-meal-prep' AS slug,'service' AS kind,'serves' AS field_key,'Serves up to' AS label,'number' AS field_type,NULL AS options,'people' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-meal-prep' AS slug,'service' AS kind,'dietary' AS field_key,'Dietary options' AS label,'multiselect' AS field_type,'["Vegetarian", "Vegan", "Gluten-free", "Halal", "Kosher"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-event-catering' AS slug,'service' AS kind,'cuisine' AS field_key,'Cuisine' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-event-catering' AS slug,'service' AS kind,'serves' AS field_key,'Serves up to' AS label,'number' AS field_type,NULL AS options,'people' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-event-catering' AS slug,'service' AS kind,'dietary' AS field_key,'Dietary options' AS label,'multiselect' AS field_type,'["Vegetarian", "Vegan", "Gluten-free", "Halal", "Kosher"]' AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-hotel-bookings' AS slug,'service' AS kind,'destination' AS field_key,'Destination' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-hotel-bookings' AS slug,'service' AS kind,'duration_days' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'days' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-hotel-bookings' AS slug,'service' AS kind,'group_size' AS field_key,'Group size' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-airport-transfers' AS slug,'service' AS kind,'destination' AS field_key,'Destination' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-airport-transfers' AS slug,'service' AS kind,'duration_days' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'days' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-airport-transfers' AS slug,'service' AS kind,'group_size' AS field_key,'Group size' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-safari-packages' AS slug,'service' AS kind,'destination' AS field_key,'Destination' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-safari-packages' AS slug,'service' AS kind,'duration_days' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'days' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-safari-packages' AS slug,'service' AS kind,'group_size' AS field_key,'Group size' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-domestic-tours' AS slug,'service' AS kind,'destination' AS field_key,'Destination' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-domestic-tours' AS slug,'service' AS kind,'duration_days' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'days' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-domestic-tours' AS slug,'service' AS kind,'group_size' AS field_key,'Group size' AS label,'number' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-vet-visits' AS slug,'service' AS kind,'pet_type' AS field_key,'Pet type' AS label,'select' AS field_type,'["Dog", "Cat", "Bird", "Fish", "Other"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-vet-visits' AS slug,'service' AS kind,'at_home' AS field_key,'At your home' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-vet-visits' AS slug,'service' AS kind,'duration_hours' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-pet-boarding' AS slug,'service' AS kind,'pet_type' AS field_key,'Pet type' AS label,'select' AS field_type,'["Dog", "Cat", "Bird", "Fish", "Other"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-pet-boarding' AS slug,'service' AS kind,'at_home' AS field_key,'At your home' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-pet-boarding' AS slug,'service' AS kind,'duration_hours' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-pet-grooming' AS slug,'service' AS kind,'pet_type' AS field_key,'Pet type' AS label,'select' AS field_type,'["Dog", "Cat", "Bird", "Fish", "Other"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-pet-grooming' AS slug,'service' AS kind,'at_home' AS field_key,'At your home' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-pet-grooming' AS slug,'service' AS kind,'duration_hours' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-dog-walking' AS slug,'service' AS kind,'pet_type' AS field_key,'Pet type' AS label,'select' AS field_type,'["Dog", "Cat", "Bird", "Fish", "Other"]' AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-dog-walking' AS slug,'service' AS kind,'at_home' AS field_key,'At your home' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-dog-walking' AS slug,'service' AS kind,'duration_hours' AS field_key,'Duration' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-special-needs' AS slug,'service' AS kind,'age_range' AS field_key,'Age range' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-special-needs' AS slug,'service' AS kind,'at_home' AS field_key,'At your home' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-special-needs' AS slug,'service' AS kind,'hours' AS field_key,'Hours' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-liveout-nanny' AS slug,'service' AS kind,'age_range' AS field_key,'Age range' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-liveout-nanny' AS slug,'service' AS kind,'at_home' AS field_key,'At your home' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-liveout-nanny' AS slug,'service' AS kind,'hours' AS field_key,'Hours' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-evening-babysitting' AS slug,'service' AS kind,'age_range' AS field_key,'Age range' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-evening-babysitting' AS slug,'service' AS kind,'at_home' AS field_key,'At your home' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-evening-babysitting' AS slug,'service' AS kind,'hours' AS field_key,'Hours' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
UNION ALL
SELECT 'svc-daytime-nanny' AS slug,'service' AS kind,'age_range' AS field_key,'Age range' AS label,'text' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,1 AS is_required,10 AS sort_order
UNION ALL
SELECT 'svc-daytime-nanny' AS slug,'service' AS kind,'at_home' AS field_key,'At your home' AS label,'boolean' AS field_type,NULL AS options,'' AS unit,'' AS placeholder,0 AS is_required,20 AS sort_order
UNION ALL
SELECT 'svc-daytime-nanny' AS slug,'service' AS kind,'hours' AS field_key,'Hours' AS label,'number' AS field_type,NULL AS options,'hrs' AS unit,'' AS placeholder,0 AS is_required,30 AS sort_order
) s
JOIN `store_categories` c ON c.slug = s.slug
WHERE NOT EXISTS (
  SELECT 1 FROM `store_attribute_defs` d
  WHERE d.category_id = c.id AND d.field_key = s.field_key
);
