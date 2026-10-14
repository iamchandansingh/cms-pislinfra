import type { Schema, Struct } from '@strapi/strapi';

export interface CsrCard extends Struct.ComponentSchema {
  collectionName: 'components_csr_cards';
  info: {
    description: '';
    displayName: 'Card';
  };
  attributes: {
    description: Schema.Attribute.Text;
    image: Schema.Attribute.Media<'images'>;
    imageLeft: Schema.Attribute.Boolean & Schema.Attribute.DefaultTo<true>;
    title: Schema.Attribute.String;
  };
}

export interface CsrFocusArea extends Struct.ComponentSchema {
  collectionName: 'components_csr_focus_areas';
  info: {
    description: '';
    displayName: 'Focus Area';
  };
  attributes: {
    description: Schema.Attribute.Text;
    iconColor: Schema.Attribute.String;
    iconName: Schema.Attribute.String;
    image: Schema.Attribute.Media<'images'>;
    title: Schema.Attribute.String;
  };
}

export interface CsrStat extends Struct.ComponentSchema {
  collectionName: 'components_csr_stats';
  info: {
    description: '';
    displayName: 'Stat';
  };
  attributes: {
    iconColor: Schema.Attribute.String;
    iconName: Schema.Attribute.String;
    label: Schema.Attribute.String;
    suffix: Schema.Attribute.String;
    value: Schema.Attribute.Integer;
  };
}

export interface SharedApproachItem extends Struct.ComponentSchema {
  collectionName: 'components_shared_approach_items';
  info: {
    displayName: 'Approach Item';
    icon: 'bullseye';
  };
  attributes: {
    points: Schema.Attribute.JSON;
    position: Schema.Attribute.String;
    year: Schema.Attribute.String;
  };
}

export interface SharedAwardCertItem extends Struct.ComponentSchema {
  collectionName: 'components_shared_award_cert_items';
  info: {
    displayName: 'Award Cert Item';
    icon: 'medal';
  };
  attributes: {
    category: Schema.Attribute.String;
    clientImage: Schema.Attribute.Media<'images'>;
    description: Schema.Attribute.Text;
    image: Schema.Attribute.Media<'images'>;
    pdf: Schema.Attribute.Media<'files'>;
    title: Schema.Attribute.String;
    videoUrl: Schema.Attribute.String;
    year: Schema.Attribute.String;
  };
}

export interface SharedAwardItem extends Struct.ComponentSchema {
  collectionName: 'components_shared_award_items';
  info: {
    displayName: 'Award Item';
    icon: 'award';
  };
  attributes: {
    description: Schema.Attribute.Text;
    image: Schema.Attribute.Media<'images'> & Schema.Attribute.Required;
    subtitle: Schema.Attribute.String;
    title: Schema.Attribute.String;
  };
}

export interface SharedFeatureCard extends Struct.ComponentSchema {
  collectionName: 'components_shared_feature_cards';
  info: {
    description: '';
    displayName: 'Feature Card';
    icon: 'star';
  };
  attributes: {
    description: Schema.Attribute.Text & Schema.Attribute.Required;
    iconName: Schema.Attribute.String;
    title: Schema.Attribute.String & Schema.Attribute.Required;
  };
}

export interface SharedFeatureItem extends Struct.ComponentSchema {
  collectionName: 'components_shared_feature_items';
  info: {
    displayName: 'Feature Item';
    icon: 'check';
  };
  attributes: {
    description: Schema.Attribute.Text;
    iconName: Schema.Attribute.String;
    title: Schema.Attribute.String;
  };
}

export interface SharedFooterLink extends Struct.ComponentSchema {
  collectionName: 'components_shared_footer_links';
  info: {
    description: 'Simple link for footer menus';
    displayName: 'Footer Link';
  };
  attributes: {
    href: Schema.Attribute.String & Schema.Attribute.Required;
    label: Schema.Attribute.String & Schema.Attribute.Required;
  };
}

export interface SharedGalleryItem extends Struct.ComponentSchema {
  collectionName: 'components_shared_gallery_items';
  info: {
    displayName: 'Gallery Item';
    icon: 'image';
  };
  attributes: {
    image: Schema.Attribute.Media<'images'> & Schema.Attribute.Required;
    title: Schema.Attribute.String;
  };
}

export interface SharedHighlight extends Struct.ComponentSchema {
  collectionName: 'components_shared_highlights';
  info: {
    displayName: 'Highlight';
    icon: 'star';
  };
  attributes: {
    description: Schema.Attribute.String;
    iconName: Schema.Attribute.String;
    title: Schema.Attribute.String;
  };
}

export interface SharedMapMarker extends Struct.ComponentSchema {
  collectionName: 'components_shared_map_markers';
  info: {
    description: '';
    displayName: 'Map Marker';
  };
  attributes: {
    city: Schema.Attribute.String & Schema.Attribute.Required;
    cx: Schema.Attribute.Decimal & Schema.Attribute.Required;
    cy: Schema.Attribute.Decimal & Schema.Attribute.Required;
  };
}

export interface SharedNavLink extends Struct.ComponentSchema {
  collectionName: 'components_shared_nav_links';
  info: {
    description: 'Main navigation item (can have dropdown children)';
    displayName: 'Nav Link';
  };
  attributes: {
    href: Schema.Attribute.String & Schema.Attribute.Required;
    label: Schema.Attribute.String & Schema.Attribute.Required;
    subLinks: Schema.Attribute.Component<'shared.sub-link', true>;
  };
}

export interface SharedPersonCard extends Struct.ComponentSchema {
  collectionName: 'components_shared_person_cards';
  info: {
    displayName: 'Person Card';
    icon: 'user';
  };
  attributes: {
    bio: Schema.Attribute.Text;
    department: Schema.Attribute.String;
    designation: Schema.Attribute.String;
    image: Schema.Attribute.Media<'images'>;
    name: Schema.Attribute.String;
  };
}

export interface SharedPhilosophyItem extends Struct.ComponentSchema {
  collectionName: 'components_shared_philosophy_items';
  info: {
    displayName: 'Philosophy Item';
    icon: 'image';
  };
  attributes: {
    description: Schema.Attribute.Text;
    image: Schema.Attribute.Media<'images'> & Schema.Attribute.Required;
    title: Schema.Attribute.String;
  };
}

export interface SharedPillarItem extends Struct.ComponentSchema {
  collectionName: 'components_shared_pillar_items';
  info: {
    displayName: 'Pillar Item';
    icon: 'columns';
  };
  attributes: {
    description: Schema.Attribute.Text;
    title: Schema.Attribute.String;
  };
}

export interface SharedPolicySection extends Struct.ComponentSchema {
  collectionName: 'components_shared_policy_sections';
  info: {
    description: '';
    displayName: 'Policy Section';
  };
  attributes: {
    content: Schema.Attribute.RichText & Schema.Attribute.Required;
    icon: Schema.Attribute.String &
      Schema.Attribute.Required &
      Schema.Attribute.DefaultTo<'FileText'>;
    title: Schema.Attribute.String & Schema.Attribute.Required;
  };
}

export interface SharedProgramItem extends Struct.ComponentSchema {
  collectionName: 'components_shared_program_items';
  info: {
    displayName: 'Program Item';
    icon: 'clipboard-list';
  };
  attributes: {
    items: Schema.Attribute.JSON;
    title: Schema.Attribute.String;
  };
}

export interface SharedReport extends Struct.ComponentSchema {
  collectionName: 'components_shared_reports';
  info: {
    description: '';
    displayName: 'Report';
    icon: 'file-pdf';
  };
  attributes: {
    desc: Schema.Attribute.Text;
    fileUrl: Schema.Attribute.String;
    pdfFile: Schema.Attribute.Media<'files'>;
    size: Schema.Attribute.String & Schema.Attribute.DefaultTo<'PDF'>;
    title: Schema.Attribute.String & Schema.Attribute.Required;
    year: Schema.Attribute.String;
  };
}

export interface SharedResourceItem extends Struct.ComponentSchema {
  collectionName: 'components_shared_resource_items';
  info: {
    description: 'Downloadable safety documents, presentations and policies';
    displayName: 'Resource Item';
    icon: 'file-pdf';
  };
  attributes: {
    coverImage: Schema.Attribute.Media<'images'>;
    desc: Schema.Attribute.Text;
    file: Schema.Attribute.Media<'files' | 'images'>;
    fileUrl: Schema.Attribute.String;
    title: Schema.Attribute.String;
  };
}

export interface SharedRuleItem extends Struct.ComponentSchema {
  collectionName: 'components_shared_rule_items';
  info: {
    displayName: 'Rule Item';
    icon: 'exclamation-triangle';
  };
  attributes: {
    descEn: Schema.Attribute.Text;
    descHi: Schema.Attribute.Text;
    imgBase64Key: Schema.Attribute.String;
    titleEn: Schema.Attribute.String;
  };
}

export interface SharedSeo extends Struct.ComponentSchema {
  collectionName: 'components_shared_seos';
  info: {
    description: 'Advanced SEO metadata configuration';
    displayName: 'seo';
    icon: 'search';
  };
  attributes: {
    breadcrumbSchema: Schema.Attribute.Boolean &
      Schema.Attribute.DefaultTo<true>;
    canonicalUrl: Schema.Attribute.String;
    noFollow: Schema.Attribute.Boolean & Schema.Attribute.DefaultTo<false>;
    noIndex: Schema.Attribute.Boolean & Schema.Attribute.DefaultTo<false>;
    ogDescription: Schema.Attribute.Text;
    ogImage: Schema.Attribute.Media<'images'>;
    ogTitle: Schema.Attribute.String;
    ogType: Schema.Attribute.String & Schema.Attribute.DefaultTo<'website'>;
    organizationSchema: Schema.Attribute.Boolean &
      Schema.Attribute.DefaultTo<false>;
    schemaType: Schema.Attribute.String & Schema.Attribute.DefaultTo<'WebPage'>;
    seoDescription: Schema.Attribute.Text;
    seoKeywords: Schema.Attribute.String;
    seoTitle: Schema.Attribute.String;
    structuredData: Schema.Attribute.Text;
    tags: Schema.Attribute.String;
    twitterCardType: Schema.Attribute.String &
      Schema.Attribute.DefaultTo<'summary_large_image'>;
    twitterDescription: Schema.Attribute.Text;
    twitterImage: Schema.Attribute.Media<'images'>;
    twitterTitle: Schema.Attribute.String;
  };
}

export interface SharedSolutionGrid extends Struct.ComponentSchema {
  collectionName: 'components_shared_solution_grids';
  info: {
    description: 'Grid of feature cards for solutions pages';
    displayName: 'Solution Grid';
  };
  attributes: {
    cardBg: Schema.Attribute.String;
    cardBorder: Schema.Attribute.String;
    description: Schema.Attribute.Text;
    iconBg: Schema.Attribute.String;
    iconColor: Schema.Attribute.String;
    items: Schema.Attribute.Component<'shared.feature-card', true>;
    minWidth: Schema.Attribute.String;
    title: Schema.Attribute.String & Schema.Attribute.Required;
    vertical: Schema.Attribute.Boolean & Schema.Attribute.DefaultTo<false>;
  };
}

export interface SharedStatItem extends Struct.ComponentSchema {
  collectionName: 'components_shared_stat_items';
  info: {
    displayName: 'Stat Item';
    icon: 'chart-pie';
  };
  attributes: {
    iconName: Schema.Attribute.String;
    label: Schema.Attribute.String;
    sub: Schema.Attribute.String;
    suffix: Schema.Attribute.String;
    value: Schema.Attribute.String;
  };
}

export interface SharedStep extends Struct.ComponentSchema {
  collectionName: 'components_shared_steps';
  info: {
    displayName: 'Step';
    icon: 'check';
  };
  attributes: {
    description: Schema.Attribute.Text;
    stepNumber: Schema.Attribute.String & Schema.Attribute.Required;
    title: Schema.Attribute.String & Schema.Attribute.Required;
  };
}

export interface SharedSubLink extends Struct.ComponentSchema {
  collectionName: 'components_shared_sub_links';
  info: {
    description: 'Dropdown menu link with an optional icon';
    displayName: 'Sub Link';
  };
  attributes: {
    href: Schema.Attribute.String & Schema.Attribute.Required;
    icon: Schema.Attribute.Enumeration<
      ['FaBuilding', 'FaUsers', 'FaShieldAlt', 'FaAward', 'FaHeart']
    >;
    label: Schema.Attribute.String & Schema.Attribute.Required;
  };
}

declare module '@strapi/strapi' {
  export namespace Public {
    export interface ComponentSchemas {
      'csr.card': CsrCard;
      'csr.focus-area': CsrFocusArea;
      'csr.stat': CsrStat;
      'shared.approach-item': SharedApproachItem;
      'shared.award-cert-item': SharedAwardCertItem;
      'shared.award-item': SharedAwardItem;
      'shared.feature-card': SharedFeatureCard;
      'shared.feature-item': SharedFeatureItem;
      'shared.footer-link': SharedFooterLink;
      'shared.gallery-item': SharedGalleryItem;
      'shared.highlight': SharedHighlight;
      'shared.map-marker': SharedMapMarker;
      'shared.nav-link': SharedNavLink;
      'shared.person-card': SharedPersonCard;
      'shared.philosophy-item': SharedPhilosophyItem;
      'shared.pillar-item': SharedPillarItem;
      'shared.policy-section': SharedPolicySection;
      'shared.program-item': SharedProgramItem;
      'shared.report': SharedReport;
      'shared.resource-item': SharedResourceItem;
      'shared.rule-item': SharedRuleItem;
      'shared.seo': SharedSeo;
      'shared.solution-grid': SharedSolutionGrid;
      'shared.stat-item': SharedStatItem;
      'shared.step': SharedStep;
      'shared.sub-link': SharedSubLink;
    }
  }
}
