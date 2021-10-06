module.exports = {
  siteMetadata: {
    title: `Commonground Gateway`,
    description: `The Commonground gateway is a ..`,
    author: `Conduction`,
    repositoryUrl: `https://github.com/ConductionNL/commonground-gateway`,
    slackUrl: `https://slack.com`,
    languages: ['en' ,'nl'],
    defaultLanguage: 'nl'
  },
  plugins: [
    `gatsby-plugin-styled-components`,
    `gatsby-plugin-react-helmet`,
    {
      resolve: `gatsby-source-filesystem`,
      options: {
        name: `pages`,
        path: `${__dirname}/src/pages`,
      },
    },
    `gatsby-transformer-sharp`,
    {
      resolve: `gatsby-transformer-remark`,
      options: {
        plugins: [
          `gatsby-remark-check-links`,
          `gatsby-remark-prismjs`,
          `gatsby-remark-images`,
        ]
      }
    },
    {
      resolve: `gatsby-plugin-plausible`,
      options: {
        domain: `demodam.org`,
        },
    },
    `gatsby-plugin-sharp`,
    {
      resolve: `gatsby-plugin-manifest`,
      options: {
        name: `Commonground Gateway`,
        short_name: `Commonground gateway`,
        start_url: `/`,
        display: `standalone`,
        icon: `src/images/conduction_logo.svg`,
      },
    },
    `gatsby-plugin-i18n`,
    {
      resolve: `gatsby-plugin-i18n`,
      options: {
        langKeyDefault: 'nl',
        useLangKeyLayout: false,
        prefixDefault: false,
      },
    },
    // this (optional) plugin enables Progressive Web App + Offline functionality
    // To learn more, visit: https://gatsby.dev/offline
    // `gatsby-plugin-offline`,
  ],
}
