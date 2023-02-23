USE [master]
GO

CREATE DATABASE [api]
    COLLATE Latin1_General_100_CI_AS_SC_UTF8;
GO

CREATE LOGIN [api-platform]
    WITH PASSWORD = '!ChangeMe!'

CREATE USER [api-platform] FOR LOGIN [api-platform] WITH DEFAULT_SCHEMA=[dbo]
GO

USE [api]
GO

/****** Object:  Table [dbo].[Cars]    Script Date: 18.10.2019 18:33:09 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO

EXEC sp_changedbowner 'api-platform'
GO
