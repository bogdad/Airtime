/*------------------------------------------------------------------------------

    Copyright (c) 2004 Media Development Loan Fund
 
    This file is part of the LiveSupport project.
    http://livesupport.campware.org/
    To report bugs, send an e-mail to bugs@campware.org
 
    LiveSupport is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
  
    LiveSupport is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
 
    You should have received a copy of the GNU General Public License
    along with LiveSupport; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 
 
    Author   : $Author$
    Version  : $Revision$
    Location : $URL$

------------------------------------------------------------------------------*/

/* ============================================================ include files */

#include <odbc++/statement.h>
#include <odbc++/preparedstatement.h>
#include <odbc++/resultset.h>
#include <libxml++/libxml++.h>

#include "LiveSupport/Core/TimeConversion.h"
#include "LiveSupport/Core/FileTools.h"
#include "LiveSupport/Db/Conversion.h"
#include "PostgresqlBackup.h"

using namespace odbc;
using namespace boost::posix_time;
using namespace xmlpp;

using namespace LiveSupport::Core;
using namespace LiveSupport::Db;
using namespace LiveSupport::StorageClient;
using namespace LiveSupport::Scheduler;


/* ===================================================  local data structures */


/* ================================================  local constants & macros */

/*------------------------------------------------------------------------------
 *  The name of the config element for this class
 *----------------------------------------------------------------------------*/
const std::string PostgresqlBackup::configElementNameStr
                                                = "postgresqlBackup";

namespace {

/*------------------------------------------------------------------------------
 *  The name of the schedule export element
 *----------------------------------------------------------------------------*/
const std::string   scheduleExportElementName   = "scheduleExport";


/*------------------------------------------------------------------------------
 *  The name of the fromTime attribute
 *----------------------------------------------------------------------------*/
const std::string   fromTimeAttrName    = "fromTime";

/*------------------------------------------------------------------------------
 *  The name of the toTime attribute
 *----------------------------------------------------------------------------*/
const std::string   toTimeAttrName      = "toTime";


/*------------------------------------------------------------------------------
 *  The working backup state
 *----------------------------------------------------------------------------*/
const std::string    workingState       = "working";

/*------------------------------------------------------------------------------
 *  The finished / success backup state
 *----------------------------------------------------------------------------*/
const std::string    successState       = "success";

/*------------------------------------------------------------------------------
 *  The finished / failure backup state
 *----------------------------------------------------------------------------*/
const std::string    failureState       = "fault";


/*------------------------------------------------------------------------------
 *  The name of the schedule export fie in the export tarbal;
 *----------------------------------------------------------------------------*/
const std::string    scheduleExportFileName     = "meta-inf/scheduler.xml";


/*------------------------------------------------------------------------------
 *  A statement to check if the database can be accessed.
 *----------------------------------------------------------------------------*/
const std::string   check1Stmt          = "SELECT 1";

/*------------------------------------------------------------------------------
 *  The SQL create statement, used for installation.
 *----------------------------------------------------------------------------*/
const std::string   createStmt =
    "CREATE TABLE backup\n"
    "(\n"
    "   token       VARCHAR(64)     NOT NULL,\n"
    "   sessionId   VARCHAR(64)     NOT NULL,\n"
    "   status      VARCHAR(32)     NOT NULL,\n"
    "   fromTime    TIMESTAMP       NOT NULL,\n"
    "   toTime      TIMESTAMP       NOT NULL,\n"
    "\n"
    "   PRIMARY KEY(token)\n"
    ");";

/*------------------------------------------------------------------------------
 *  The SQL create statement, used for installation.
 *----------------------------------------------------------------------------*/
const std::string   dropStmt            = "DROP TABLE backup;";

/*------------------------------------------------------------------------------
 *  A statement to check if the backup table exists.
 *----------------------------------------------------------------------------*/
const std::string   backupCountStmt     = "SELECT COUNT(*) FROM backup";

/*------------------------------------------------------------------------------
 *  A statement to store a backup entry.
 *  - token - the token of the backup
 *  - status - the status of the backup, either 'working', 'success' or 'fault'
 *  - fromTime - the start time of the schedule backup
 *  - toTime - the end time of the schedule backup
 *----------------------------------------------------------------------------*/
const std::string   storeBackupStmt =
             "INSERT INTO backup(token, sessionId, status, fromTime, toTime) "
                     "VALUES(?, ?, ?, ?, ?)";

/*------------------------------------------------------------------------------
 *  Get a backup from the database.
 *  - token - the token of an existing backup
 *----------------------------------------------------------------------------*/
const std::string   getBackupStmt =
             "SELECT token, sessionId, status, fromTime, toTime FROM backup "
                    "WHERE token = ?";

/*------------------------------------------------------------------------------
 *  A statement to update a backup entry.
 *  - status - the new status of the backup
 *  - token - the token of an existing backup
 *----------------------------------------------------------------------------*/
const std::string   updateBackupStmt =
                                "UPDATE backup SET status = ? WHERE token = ?";

/*------------------------------------------------------------------------------
 *  A statement to delete a backup entry
 *  - token - the token of an existing backup
 *----------------------------------------------------------------------------*/
const std::string   deleteBackupStmt    = "DELETE FROM backup WHERE token = ?";

}

/* ===============================================  local function prototypes */


/* =============================================================  module code */

/*------------------------------------------------------------------------------
 *  Configure the backup.
 *----------------------------------------------------------------------------*/
void
PostgresqlBackup :: configure(const xmlpp::Element & element)
                                                throw (std::invalid_argument,
                                                       std::logic_error)
{
    if (element.get_name() != configElementNameStr) {
        std::string eMsg = "Bad configuration element ";
        eMsg += element.get_name();
        throw std::invalid_argument(eMsg);
    }

    // nothing to do here, really
}


/*------------------------------------------------------------------------------
 *  Install the PostgresqlBackup.
 *----------------------------------------------------------------------------*/
void
PostgresqlBackup :: install(void)                     throw (std::exception)
{
    if (!isInstalled()) {
        Ptr<Connection>::Ref    conn;
        try {
            conn = connectionManager->getConnection();
            Ptr<Statement>::Ref     stmt(conn->createStatement());
            stmt->execute(createStmt);
            connectionManager->returnConnection(conn);
        } catch (std::exception &e) {
            if (conn) {
                connectionManager->returnConnection(conn);
            }
            throw;
        }
    }
}


/*------------------------------------------------------------------------------
 *  Check to see if the PostgresqlBackup has already been installed.
 *----------------------------------------------------------------------------*/
bool
PostgresqlBackup :: isInstalled(void)                 throw (std::exception)
{
    Ptr<Connection>::Ref    conn;
    try {
        Ptr<Statement>::Ref     stmt;
        ResultSet             * res;

        conn = connectionManager->getConnection();

        // see if we can connect at all
        stmt.reset(conn->createStatement());
        stmt->execute(check1Stmt);
        res = stmt->getResultSet();
        if (!res->next() || (res->getInt(1) != 1)) {
            throw std::runtime_error("Can't connect to database");
        }

        // see if the backup table exists
        try {
            stmt.reset(conn->createStatement());
            stmt->execute(backupCountStmt);
            res = stmt->getResultSet();
            if (!res->next() || (res->getInt(1) < 0)) {
                connectionManager->returnConnection(conn);
                return false;
            }
        } catch (std::exception &e) {
            connectionManager->returnConnection(conn);
            return false;
        }

        connectionManager->returnConnection(conn);
    } catch (std::exception &e) {
        if (conn) {
            connectionManager->returnConnection(conn);
        }
        throw;
    }

    return true;
}


/*------------------------------------------------------------------------------
 *  Uninstall the PostgresqlBackup.
 *----------------------------------------------------------------------------*/
void
PostgresqlBackup :: uninstall(void)                   throw (std::exception)
{
    Ptr<Connection>::Ref    conn;
    try {
        conn = connectionManager->getConnection();
        Ptr<Statement>::Ref     stmt(conn->createStatement());
        stmt->execute(dropStmt);
        connectionManager->returnConnection(conn);
    } catch (std::exception &e) {
        if (conn) {
            connectionManager->returnConnection(conn);
        }
        throw;
    }
}


/*------------------------------------------------------------------------------
 *  Start a backup process.
 *----------------------------------------------------------------------------*/
Ptr<Glib::ustring>::Ref
PostgresqlBackup ::createBackupOpen(Ptr<SessionId>::Ref        sessionId,
                                    Ptr<SearchCriteria>::Ref   criteria,
                                    Ptr<ptime>::Ref            fromTime,
                                    Ptr<ptime>::Ref            toTime)
                                                        throw (XmlRpcException)
{
    Ptr<Glib::ustring>::Ref     token;
    Ptr<Connection>::Ref        conn;
    bool                        result = false;

    // open up a backup process with the storage server
    token = storage->createBackupOpen(sessionId, criteria);

    // store the details of the backup, with a pending status
    try {
        conn = connectionManager->getConnection();
        Ptr<Timestamp>::Ref         timestamp;
        Ptr<PreparedStatement>::Ref pstmt(conn->prepareStatement(
                                                        storeBackupStmt));
        pstmt->setString(1, *token);
        pstmt->setString(2, sessionId->getId());
        pstmt->setString(3, asyncStateToString(AsyncState::pendingState));

        timestamp = Conversion::ptimeToTimestamp(fromTime);
        pstmt->setTimestamp(4, *timestamp);

        timestamp = Conversion::ptimeToTimestamp(toTime);
        pstmt->setTimestamp(5, *timestamp);

        result = pstmt->executeUpdate() == 1;

        connectionManager->returnConnection(conn);
    } catch (std::exception &e) {
        if (conn) {
            connectionManager->returnConnection(conn);
        }
        throw std::invalid_argument(e.what());
    }

    if (!result) {
        throw std::invalid_argument("couldn't insert into database");
    }

    return token;
}


/*------------------------------------------------------------------------------
 *  Check on the status of a backup process.
 *----------------------------------------------------------------------------*/
AsyncState
PostgresqlBackup ::createBackupCheck(
                            const Glib::ustring &             token,
                            Ptr<const Glib::ustring>::Ref &   url,
                            Ptr<const Glib::ustring>::Ref &   path,
                            Ptr<const Glib::ustring>::Ref &   errorMessage)
                                                        throw (XmlRpcException)
{
    Ptr<Connection>::Ref                conn;
    AsyncState                          status;
    Ptr<ptime>::Ref                     fromTime;
    Ptr<ptime>::Ref                     toTime;
    bool                                result;

    // first, check on the status ourselves
    try {
        Ptr<Timestamp>::Ref     timestamp;

        conn = connectionManager->getConnection();
        Ptr<PreparedStatement>::Ref pstmt(conn->prepareStatement(
                                                                getBackupStmt));

        pstmt->setString(1, token);

        Ptr<ResultSet>::Ref     rs(pstmt->executeQuery());
        if (rs->next()) {
            status = stringToAsyncState(rs->getString(3));
            
            timestamp.reset(new Timestamp(rs->getTimestamp(4)));
            fromTime = Conversion::timestampToPtime(timestamp);

            timestamp.reset(new Timestamp(rs->getTimestamp(5)));
            toTime = Conversion::timestampToPtime(timestamp);
        }

        connectionManager->returnConnection(conn);
    } catch (std::exception &e) {
        if (conn) {
            connectionManager->returnConnection(conn);
        }
        // TODO: report error
        return status;
    }

    if (status == AsyncState::pendingState) {
        status = storage->createBackupCheck(token, url, path, errorMessage);

        if (status == AsyncState::finishedState) {
            putScheduleExportIntoTar(path, fromTime, toTime);
        }
    }

    // update the status
    try {
        conn = connectionManager->getConnection();
        Ptr<Timestamp>::Ref         timestamp;
        Ptr<PreparedStatement>::Ref pstmt(conn->prepareStatement(
                                                        updateBackupStmt));
        pstmt->setString(1, asyncStateToString(status));
        pstmt->setString(2, token);

        result = pstmt->executeUpdate() == 1;

        connectionManager->returnConnection(conn);
    } catch (std::exception &e) {
        if (conn) {
            connectionManager->returnConnection(conn);
        }
        throw std::invalid_argument(e.what());
    }

    if (!result) {
        throw std::invalid_argument("couldn't insert into database");
    }

    return status;
}


/*------------------------------------------------------------------------------
 *  Close a backup process, and free up all resources.
 *----------------------------------------------------------------------------*/
void
PostgresqlBackup ::putScheduleExportIntoTar(
                        Ptr<const Glib::ustring>::Ref & path,
                        Ptr<ptime>::Ref                 fromTime,
                        Ptr<ptime>::Ref                 toTime)
                                                    throw (std::runtime_error)
{
    Ptr<Document>::Ref      document(new Document());
    Element               * root = document->create_root_node("scheduler");
    std::string             tmpFileName = FileTools::tempnam();

    // create the export, and write it to a temporary file
    schedule->exportScheduleEntries(root, fromTime, toTime);
    document->write_to_file(tmpFileName);

    try {
        FileTools::appendFileToTarball(*path,
                                       tmpFileName,
                                       scheduleExportFileName);
    } catch (std::runtime_error &e) {
        remove(tmpFileName.c_str());
        throw;
    }

    remove(tmpFileName.c_str());
}


/*------------------------------------------------------------------------------
 *  Close a backup process, and free up all resources.
 *----------------------------------------------------------------------------*/
void
PostgresqlBackup ::createBackupClose(const Glib::ustring &    token)
                                                        throw (XmlRpcException)
{
    Ptr<Connection>::Ref        conn;
    bool                        result;

    storage->createBackupClose(token);

    // delete the backup from our database
    try {
        conn = connectionManager->getConnection();
        Ptr<Timestamp>::Ref         timestamp;
        Ptr<PreparedStatement>::Ref pstmt(conn->prepareStatement(
                                                        deleteBackupStmt));
        pstmt->setString(1, token);

        result = pstmt->executeUpdate() == 1;

        connectionManager->returnConnection(conn);
    } catch (std::exception &e) {
        if (conn) {
            connectionManager->returnConnection(conn);
        }
        throw std::invalid_argument(e.what());
    }

    if (!result) {
        throw std::invalid_argument("couldn't insert into database");
    }
}


/*------------------------------------------------------------------------------
 *  Convert a string status to an AsyncState.
 *----------------------------------------------------------------------------*/
AsyncState
PostgresqlBackup ::stringToAsyncState(const std::string &     statusString)
                                                                    throw ()
{
    return AsyncState::fromBackupString(statusString);
}


/*------------------------------------------------------------------------------
 *  Convert an AsyncState to a string.
 *----------------------------------------------------------------------------*/
std::string
PostgresqlBackup ::asyncStateToString(AsyncState    status)
                                                                    throw ()
{
    return *status.toBackupString();
}
