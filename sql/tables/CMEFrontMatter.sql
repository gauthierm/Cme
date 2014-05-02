create table CMEFrontMatter (
	id serial,

	provider integer not null references CMEProvider(id) on delete cascade,
	evaluation integer references Inquisition(id) on delete set null,

	enabled boolean not null default true,
	objectives text,
	planning_committee_no_disclosures text,
	support_staff_no_disclosures text,
	review_date timestamp,

	primary key(id)
);

create index CMEFrontMatter_provider_index on CMEFrontMatter(provider);
