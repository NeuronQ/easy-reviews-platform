select p.id, p.post_title, tt.term_taxonomy_id, tt.taxonomy, t.term_id, t.slug, t.name
from
	wp_posts p,
	wp_term_relationships tr,
	wp_term_taxonomy tt,
	wp_terms t
where
	p.id = tr.object_id and
	tr.term_taxonomy_id = tt.term_taxonomy_id and
	tt.term_id = t.term_id and
	p.id = 59;

-- get posts by category
select p.id, p.post_title, t.slug, tt.taxonomy
from
	wp_posts p,
	wp_term_relationships tr,
	wp_term_taxonomy tt,
	wp_terms t
where
	p.ID = tr.object_id and
	tr.term_taxonomy_id = 16 and
	tr.term_taxonomy_id = tt.term_taxonomy_id and
	tt.term_id = t.term_id and
	p.post_status = 'publish';

-- get published posts by category with metas
select p.id, p.post_title, t.slug, tt.taxonomy, m.meta_value as average_rating_overall
from
	wp_posts p
	left join wp_postmeta m on m.post_id = p.ID and m.meta_key = 'average_rating_overall',
	wp_term_relationships tr,
	wp_term_taxonomy tt,
	wp_terms t
where
	p.ID = tr.object_id
	and tr.term_taxonomy_id = 16
	and tr.term_taxonomy_id = tt.term_taxonomy_id
	and tt.term_id = t.term_id
	and p.post_status = 'publish'
group by p.id;

-- get published posts by category with metas
select p.id, p.post_title, m.meta_value as average_rating_overall
from
	wp_posts p
	left join wp_postmeta m on m.post_id = p.ID and m.meta_key = 'average_rating_overall'
	join wp_term_relationships tr on p.ID = tr.object_id
where
	tr.term_taxonomy_id = 16
	and p.post_status = 'publish'
group by p.id
order by average_rating_overall desc;

-- get published posts by category ranked by meta average_rating_overall
select
	p.id,
	p.post_title,
	m.meta_value as average_rating_overall,
	(select count(distinct pi.id)
	from
		wp_posts pi
		left join wp_postmeta mi on mi.post_id = pi.ID and mi.meta_key = 'average_rating_overall'
		join wp_term_relationships tri on pi.ID = tri.object_id
	where
		tri.term_taxonomy_id = 16
		and pi.post_status = 'publish'
		and mi.meta_value >= m.meta_value
	) as rank
from
	wp_posts p
	left join wp_postmeta m on m.post_id = p.ID and m.meta_key = 'average_rating_overall'
	join wp_term_relationships tr on p.ID = tr.object_id
where
	tr.term_taxonomy_id = 16
	and p.post_status = 'publish'
group by p.id
order by average_rating_overall desc;

-- get published posts by category with metas
set @rownum := 0;

select
	post_id,
	post_title,
	average_rating_overall,
	rank
from
	(select
		p.id as post_id,
		p.post_title as post_title,
		m.meta_value as average_rating_overall,
		@rownum := @rownum + 1 as rank
	from
		wp_posts p
		left join wp_postmeta m on m.post_id = p.ID and m.meta_key = 'average_rating_overall'
		join wp_term_relationships tr on p.ID = tr.object_id
	where
		tr.term_taxonomy_id = 16
		and p.post_status = 'publish'
	group by p.id
	order by average_rating_overall desc
	) as result;






set @rownum := 0;
select
	ID,
	post_title,
	m.meta_value as global_average_rating_overall,
	@rownum := @rownum + 1 as rank
from
	wp_posts p
	left join wp_postmeta m on m.post_id = p.ID and m.meta_key = 'global_average_rating_overall'
where
	p.post_status = 'publish'
order by global_average_rating_overall desc;




select
	p.ID,
	p.post_title,
	m_garo.meta_value as global_average_rating_overall,
	m_lrb.meta_value as last_rated_by,
	m_ero.meta_value as editor_rating_overall,
	cm.meta_value as last_user_rating_overall,
	(
		select count(distinct pi.id)
		from
			wp_posts pi
			left join wp_postmeta mi on mi.post_id = pi.ID and mi.meta_key = 'global_average_rating_overall'
			join wp_term_relationships tri on pi.ID = tri.object_id and tri.term_taxonomy_id = %d
		where
			pi.post_type = 'easyrp_review' and
			pi.post_status = 'publish' and
			mi.meta_value >= m_garo.meta_value
	) as rank
from
	wp_posts p
	left join wp_postmeta m_garo on m_garo.post_id = p.ID and m_garo.meta_key = 'global_average_rating_overall'
	left join wp_postmeta m_ero on m_ero.post_id = p.ID and m_ero.meta_key = 'editor_rating_overall'
	left join wp_postmeta m_lrb on m_lrb.post_id = p.ID and m_lrb.meta_key = 'last_rated_by'
	left join wp_comments c on c.comment_ID = m_lrb.meta_value
	left join wp_commentmeta cm on cm.comment_id = c.comment_ID and cm.meta_key = 'rating_overall'
	join wp_term_relationships tr on p.ID = tr.object_id and tr.term_taxonomy_id = %d
where
	p.post_type = 'easyrp_review' and				
	p.post_status = 'publish'
group by p.ID
order by global_average_rating_overall desc
limit %d;

select
	p.ID,
	p.post_title,
	m_garo.meta_value as global_average_rating_overall,
	m_lrb.meta_value as last_rated_by,
	m_ero.meta_value as editor_rating_overall,
	cm.meta_value as last_user_rating_overall,
	(
		select count(distinct pi.id)
		from
			wp_posts pi
			left join wp_postmeta mi on mi.post_id = pi.ID and mi.meta_key = 'global_average_rating_overall'
		where
			pi.post_type = 'easyrp_review' and
			pi.post_status = 'publish' and
			mi.meta_value >= m_garo.meta_value
	) as rank
from
	wp_posts p
	left join wp_postmeta m_garo on m_garo.post_id = p.ID and m_garo.meta_key = 'global_average_rating_overall'
	left join wp_postmeta m_ero on m_ero.post_id = p.ID and m_ero.meta_key = 'editor_rating_overall'
	left join wp_postmeta m_lrb on m_lrb.post_id = p.ID and m_lrb.meta_key = 'last_rated_by'
	left join wp_comments c on c.comment_ID = m_lrb.meta_value
	left join wp_commentmeta cm on cm.comment_id = c.comment_ID and cm.meta_key = 'rating_overall'
where
	p.post_type = 'easyrp_review' and				
	p.post_status = 'publish'
group by p.ID
order by global_average_rating_overall desc
limit %d;