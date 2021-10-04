module.exports = {
  pathPrefix: "/commonground-gateway",
  siteMetadata: {
    siteUrl: "https://www.yourdomain.tld",
    title: "Commonground Gateway",
  },
  plugins: [
    "gatsby-transformer-remark",
    {
      resolve: "gatsby-source-filesystem",
      options: {
        name: "pages",
        path: "./src/pages/",
      },
      __key: "pages",
    },
  ],
};
